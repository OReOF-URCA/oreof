<?php

namespace App\Controller;

use App\DTO\DiffObject;
use App\DTO\StructureParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Ue;
use App\Repository\FormationRepository;
use App\Repository\TypeEpreuveRepository;
use App\Service\CopyStructureReport;
use App\Service\FormationDiffBuilder;
use App\Service\FormationStructureCopier;
use App\Service\VersioningStructureExtractDiff;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * Outil de comparaison d'une formation entre versions d'années universitaires
 * successives. La source (version antérieure) et la cible (version reçue,
 * modifiée) sont toutes deux choisies librement dans la lignée, à condition
 * que la source soit plus ancienne que la cible. Sens unique source → cible,
 * aucune suppression.
 */
#[Route('/administration/formation_comparaison', name: 'app_formation_comparaison_')]
class FormationComparaisonController extends BaseController
{
    public function __construct(
        private readonly FormationRepository $formationRepository,
        private readonly EntityManagerInterface $em,
        private readonly FormationDiffBuilder $diffBuilder,
        private readonly FormationStructureCopier $structureCopier,
        private readonly TypeEpreuveRepository $typeEpreuveRepository,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('/execute', name: 'execute', methods: ['POST'])]
    public function execute(Request $request): Response
    {
        $idCible  = (int) $request->request->get('cible');
        $idSource = (int) $request->request->get('source');
        $keys     = array_keys($request->request->all('fields'));

        if (!$this->isCsrfTokenValid('formation_comparaison_execute', $request->request->get('_token'))) {
            $this->toast('danger', 'Token CSRF invalide. Veuillez recharger la page et réessayer.');
            return $this->redirectToRoute('app_parcours_index');
        }

        $cible  = $this->formationRepository->find($idCible);
        $source = $this->formationRepository->find($idSource);

        if (!$cible || !$source) {
            $this->toast('danger', 'Formations introuvables.');
            return $this->redirectToRoute('app_parcours_index');
        }

        $this->denyUnlessCanEdit($cible);

        // La source doit être une version strictement antérieure de la cible dans la lignée
        $order = $this->lineageOrder($cible);
        if (!isset($order[$source->getId()], $order[$cible->getId()]) || $order[$source->getId()] >= $order[$cible->getId()]) {
            $this->toast('danger', "La version source n'est pas une version antérieure de la version cible.");
            return $this->redirectToRoute('app_formation_show', ['slug' => $cible->getSlug()]);
        }

        // Garde de cohérence : aucune des deux n'a changé depuis l'affichage
        if ($this->diffBuilder->hashFormation($source) !== $request->request->get('hash_source')
            || $this->diffBuilder->hashFormation($cible) !== $request->request->get('hash_cible')) {
            $this->toast('danger', "Les données ont changé depuis l'affichage de la comparaison. Veuillez relancer.");
            return $this->redirectToRoute('app_formation_comparaison_diff', [
                'formation' => $cible->getId(),
                'source'    => $source->getId(),
            ]);
        }

        // Cible(s) : la cible unique choisie, OU toutes les années plus récentes que la source
        if ($request->request->get('apply_following') === '1') {
            $chain = $this->diffBuilder->fullLineage($cible);
            $sourceIndex = $order[$source->getId()];
            $cibles = [];
            foreach ($chain as $i => $f) {
                if ($i > $sourceIndex && $this->canEdit($f)) {
                    $cibles[] = $f;
                }
            }
        } else {
            $cibles = [$cible];
        }

        // UE de structure cochées : ['<parcoursId>' => ['<ueId>' => '1', …]]
        // La copie structurelle s'applique uniquement à la cible affichée
        // (l'appariement des UE est propre à cette version).
        $structureInput = $request->request->all('structure');
        // UE non répercutées cochées pour recréation : ['<parcoursCibleId>' => ['<ueSourceId>' => '1']]
        $recreateInput = $request->request->all('recreateUe');
        // Parcours non répercutés cochés pour recréation : ['<parcoursSourceId>' => '1']
        $recreateParcoursInput = array_keys($request->request->all('recreateParcours'));
        $report = new CopyStructureReport();

        try {
        $this->em->wrapInTransaction(function () use ($cibles, $keys, $source, $structureInput, $recreateInput, $recreateParcoursInput, $cible, $report) {
            foreach ($cibles as $formationCible) {
                foreach ($keys as $key) {
                    $this->diffBuilder->applyField($formationCible, $source, $key);
                }
            }

            $cibleFormationId = $cible->getId();
            foreach ($structureInput as $parcoursId => $ueIds) {
                $parcoursCible = $this->em->getRepository(Parcours::class)->find((int) $parcoursId);
                if ($parcoursCible === null || $parcoursCible->getFormation()?->getId() !== $cibleFormationId) {
                    continue;
                }
                foreach (array_keys($ueIds) as $ueId) {
                    $ueCible = $this->em->getRepository(Ue::class)->find((int) $ueId);
                    if ($ueCible === null || $this->ueFormationId($ueCible) !== $cibleFormationId) {
                        continue;
                    }
                    $ueSource = $this->findUeCounterpart($ueCible, $source);
                    if ($ueSource !== null) {
                        $this->structureCopier->copyUe($ueSource, $ueCible, $parcoursCible, $report);
                        $report->uesCopiees++;
                    }
                }
            }

            // UE non répercutées à recréer : ['<parcoursCibleId>' => ['<ueSourceId>' => '1']]
            foreach ($recreateInput as $parcoursId => $ueIds) {
                $parcoursCible = $this->em->getRepository(Parcours::class)->find((int) $parcoursId);
                if ($parcoursCible === null || $parcoursCible->getFormation()?->getId() !== $cibleFormationId) {
                    continue;
                }
                foreach (array_keys($ueIds) as $ueSourceId) {
                    $ueSource = $this->em->getRepository(Ue::class)->find((int) $ueSourceId);
                    if ($ueSource === null || $this->ueFormationId($ueSource) !== $source->getId()) {
                        continue;
                    }
                    $this->structureCopier->recreateUe($ueSource, $parcoursCible, $report);
                }
            }

            // Parcours non répercutés à recréer dans la formation cible
            foreach ($recreateParcoursInput as $parcoursSourceId) {
                $parcoursSource = $this->em->getRepository(Parcours::class)->find((int) $parcoursSourceId);
                if ($parcoursSource === null || $parcoursSource->getFormation()?->getId() !== $source->getId()) {
                    continue;
                }
                // Garde : ne pas recréer si un homologue existe déjà dans la cible
                if ($this->parcoursDejaDansCible($parcoursSource, $cible)) {
                    continue;
                }
                $this->structureCopier->recreateParcours($parcoursSource, $cible, $report);
            }
        });
        } catch (Throwable $e) {
            error_log('[FormationComparaison] Échec de la copie structurelle : ' . $e->getMessage());
            $this->toast('danger', sprintf(
                "Une erreur est survenue pendant la copie : aucune modification n'a été enregistrée (annulation complète). Détail : %s",
                $e->getMessage()
            ));
            return $this->redirectToRoute('app_formation_comparaison_diff', [
                'formation' => $cible->getId(),
                'source'    => $source->getId(),
            ]);
        }

        $nbYears = count($cibles);
        $messages = [];
        if (count($keys) > 0) {
            $messages[] = sprintf(
                '%d champ%s récupéré%s%s',
                count($keys),
                count($keys) > 1 ? 's' : '',
                count($keys) > 1 ? 's' : '',
                $nbYears > 1 ? sprintf(' sur %d années', $nbYears) : ''
            );
        }
        if ($report->uesCopiees > 0) {
            $messages[] = sprintf('%d UE recopiée%s', $report->uesCopiees, $report->uesCopiees > 1 ? 's' : '');
        }
        if ($report->parcoursCrees > 0) {
            $messages[] = sprintf('%d parcours recréé%s', $report->parcoursCrees, $report->parcoursCrees > 1 ? 's' : '');
        }
        if ($report->uesCreees > 0) {
            $messages[] = sprintf('%d UE recréée%s', $report->uesCreees, $report->uesCreees > 1 ? 's' : '');
        }
        if ($report->ecCrees > 0) {
            $messages[] = sprintf('%d EC créé%s', $report->ecCrees, $report->ecCrees > 1 ? 's' : '');
        }
        if ($report->fichesReutilisees > 0) {
            $messages[] = sprintf('%d fiche%s mutualisée%s', $report->fichesReutilisees, $report->fichesReutilisees > 1 ? 's' : '', $report->fichesReutilisees > 1 ? 's' : '');
        }

        $this->toast('success', sprintf(
            '%s depuis %s.',
            $messages ? ucfirst(implode(' et ', $messages)) : 'Aucune modification',
            $this->diffBuilder->anneeLabel($source)
        ));

        if (!empty($report->uesNonRecreees)) {
            $this->toast('warning', sprintf(
                "%d UE non répercutée(s) n'ont pas pu être recréées (semestre cible absent) : %s.",
                count($report->uesNonRecreees),
                implode(', ', array_unique($report->uesNonRecreees))
            ));
        }

        if (!empty($report->fichesPartageesIgnorees)) {
            $this->toast('info', sprintf(
                "%d matière(s) mutualisée(s) : les champs propres à l'EC (ECTS, MCCC, volumes spécifiques) ont été appliqués. "
                . "Le contenu partagé de la fiche (libellé, heures non spécifiques) a été conservé pour ne pas impacter les autres parcours : %s.",
                count(array_unique($report->fichesPartageesIgnorees)),
                implode(', ', array_unique($report->fichesPartageesIgnorees))
            ));
        }

        return $this->redirectToRoute('app_formation_show', ['slug' => $cible->getSlug()]);
    }

    #[Route('/{formation}', name: 'diff', methods: ['GET'], requirements: ['formation' => '\d+'])]
    public function diff(Formation $formation, Request $request): Response
    {
        $chain = $this->diffBuilder->fullLineage($formation); // de la plus ancienne à la plus récente

        if (count($chain) < 2) {
            $this->toast('danger', "Cette formation n'a pas d'autre version d'année à comparer.");
            return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
        }

        $index = [];
        foreach ($chain as $i => $f) {
            $index[$f->getId()] = $i;
        }

        // Cibles possibles = versions éditables ayant au moins une version antérieure
        $targetOptions = [];
        foreach ($chain as $i => $f) {
            if ($i > 0 && $this->canEdit($f)) {
                $targetOptions[] = ['id' => $f->getId(), 'label' => $this->diffBuilder->anneeLabel($f), 'index' => $i];
            }
        }

        if (empty($targetOptions)) {
            $this->toast('danger', "Vous n'avez le droit de modifier aucune version de cette lignée.");
            return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
        }

        $editableTargetIds = array_column($targetOptions, 'id');

        // Cible : ?target= si éditable, sinon la formation de la page si éditable, sinon la 1re éditable
        $idTargetParam = (int) $request->query->get('target');
        if (in_array($idTargetParam, $editableTargetIds, true)) {
            $idTarget = $idTargetParam;
        } elseif (in_array($formation->getId(), $editableTargetIds, true)) {
            $idTarget = $formation->getId();
        } else {
            $idTarget = $editableTargetIds[0];
        }
        $target = $chain[$index[$idTarget]];
        $targetIndex = $index[$idTarget];

        // Sources possibles = toutes les versions plus anciennes que la cible
        $sourceOptions = [];
        foreach ($chain as $i => $f) {
            if ($i < $targetIndex) {
                $sourceOptions[] = ['id' => $f->getId(), 'label' => $this->diffBuilder->anneeLabel($f), 'index' => $i];
            }
        }

        // Source : ?source= si plus ancienne que la cible, sinon la version immédiatement précédente
        $idSourceParam = (int) $request->query->get('source');
        if (isset($index[$idSourceParam]) && $index[$idSourceParam] < $targetIndex) {
            $source = $chain[$index[$idSourceParam]];
        } else {
            $source = $chain[$targetIndex - 1];
        }

        // Années plus récentes que la source et éditables (cibles de la diffusion par champ).
        // Inclut la cible et tout ce qui la suit. La diffusion n'a de sens que s'il
        // y a plus d'une année concernée (sinon « diffuser » revient à « récupérer »).
        $sourceIndex = $index[$source->getId()];
        $broadcastYears = [];
        foreach ($chain as $i => $f) {
            if ($i > $sourceIndex && $this->canEdit($f)) {
                $broadcastYears[] = ['id' => $f->getId(), 'label' => $this->diffBuilder->anneeLabel($f)];
            }
        }

        $fields = $this->diffBuilder->buildDiffFields($source, $target);
        $differentFields = array_values(array_filter($fields, fn($f) => $f['isDifferent']));

        // Diff structurel (UE/EC/heures/MCCC) par parcours entre source et cible
        $structureParcours = $this->buildStructureDiff($source, $target);
        $structureHasAnyDiff = false;
        foreach ($structureParcours as $row) {
            if ($row['status'] !== 'identique') {
                $structureHasAnyDiff = true;
                break;
            }
        }

        return $this->render('admin/formation_comparaison/diff.html.twig', [
            'cible'            => $target,
            'source'          => $source,
            'cibleLabel'      => $this->diffBuilder->anneeLabel($target),
            'sourceLabel'     => $this->diffBuilder->anneeLabel($source),
            'targetOptions'   => $targetOptions,
            'sourceOptions'   => $sourceOptions,
            'broadcastYears'  => $broadcastYears,
            'differentFields' => $differentFields,
            'structureParcours' => $structureParcours,
            'structureHasAnyDiff' => $structureHasAnyDiff,
            'hashSource'      => $this->diffBuilder->hashFormation($source),
            'hashCible'       => $this->diffBuilder->hashFormation($target),
        ]);
    }

    /**
     * @return array<int, int> map id de formation → position dans la lignée (0 = plus ancienne)
     */
    private function lineageOrder(Formation $formation): array
    {
        $order = [];
        foreach ($this->diffBuilder->fullLineage($formation) as $i => $f) {
            $order[$f->getId()] = $i;
        }

        return $order;
    }

    /**
     * Diff structurel par parcours entre la formation source et la cible.
     * Chaque parcours de la cible est apparié à son homologue de l'année source
     * via la chaîne parcoursOrigineCopie.
     *
     * @return array<int, array{parcours: Parcours, diffStructure: VersioningStructureExtractDiff|null, hasDiff: bool, hasCounterpart: bool}>
     */
    private function buildStructureDiff(Formation $source, Formation $cible): array
    {
        $typeEpreuves = [];
        foreach ($this->typeEpreuveRepository->findAll() as $epreuve) {
            $typeEpreuves[$epreuve->getId()] = $epreuve;
        }

        $typeD = $this->typeDiplomeResolver->getFromFormation($cible);

        $rows = [];
        $matchedSourceIds = [];

        // Parcours de la cible : appariés (modifié / identique) ou ajoutés
        foreach ($cible->getParcours() as $parcours) {
            $counterpart = $this->findParcoursCounterpart($parcours, $source);

            if ($counterpart === null) {
                $rows[] = ['parcours' => $parcours, 'status' => 'cible_seul', 'diffStructure' => null];
                continue;
            }

            $matchedSourceIds[$counterpart->getId()] = true;
            $diffStructure = null;
            $hasDiff = false;

            $addRemove = [];
            if ($typeD !== null) {
                try {
                    $dtoSourceLive = $typeD->calculStructureParcours($counterpart, true, false);
                    $dtoCibleLive  = $typeD->calculStructureParcours($parcours, true, false);

                    // VersioningStructureExtractDiff attend un côté "origine" au format
                    // snapshot (MCCC en tableaux) : on fait passer la source par un
                    // round-trip de sérialisation. La cible reste live (entités MCCC).
                    $diffStructure = new VersioningStructureExtractDiff(
                        $this->toVersioningDto($dtoSourceLive),
                        $dtoCibleLive,
                        $typeEpreuves
                    );
                    $diffStructure->extractDiff();

                    // La détection ajout/suppression utilise les DTO live (accès aux entités)
                    $addRemove = $this->detectStructuralAddRemove($dtoSourceLive, $dtoCibleLive);
                    $hasDiff = $this->structureHasDiff($diffStructure) || !empty($addRemove);
                } catch (Throwable) {
                    $diffStructure = null;
                    $addRemove = [];
                }
            }

            $rows[] = [
                'parcours'      => $parcours,
                'status'        => $hasDiff ? 'modifie' : 'identique',
                'diffStructure' => $diffStructure,
                'addRemove'     => $addRemove,
            ];
        }

        // Parcours présents dans la source mais sans homologue dans la cible
        foreach ($source->getParcours() as $parcours) {
            if (!isset($matchedSourceIds[$parcours->getId()])) {
                $rows[] = ['parcours' => $parcours, 'status' => 'source_seul', 'diffStructure' => null];
            }
        }

        return $rows;
    }

    /**
     * Convertit une StructureParcours live en sa forme "snapshot" (via le même
     * round-trip de sérialisation que le versioning), pour que les MCCC soient
     * des tableaux — format attendu côté origine par VersioningStructureExtractDiff.
     */
    private function toVersioningDto(StructureParcours $dto): StructureParcours
    {
        $json = $this->serializer->serialize($dto, 'json', [
            AbstractNormalizer::GROUPS => ['DTO_json_versioning'],
            'circular_reference_limit' => 2,
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s',
        ]);

        return $this->serializer->deserialize($json, StructureParcours::class, 'json');
    }

    /**
     * Identifiant de formation auquel appartient une UE, déduit du parcours de
     * ses EC (l'UE n'a pas de lien direct vers le parcours).
     */
    private function ueFormationId(Ue $ue): ?int
    {
        foreach ($ue->getElementConstitutifs() as $ec) {
            $formationId = $ec->getParcours()?->getFormation()?->getId();
            if ($formationId !== null) {
                return $formationId;
            }
        }

        return null;
    }

    /**
     * Remonte la chaîne ueOrigineCopie jusqu'à l'UE appartenant à la formation
     * source. Sert à apparier une UE cible à son homologue de l'année source.
     */
    private function findUeCounterpart(Ue $ueCible, Formation $source): ?Ue
    {
        $current = $ueCible->getUeOrigineCopie();
        $guard = 0;
        while ($current !== null && $guard < 20) {
            if ($this->ueFormationId($current) === $source->getId()) {
                return $current;
            }
            $current = $current->getUeOrigineCopie();
            $guard++;
        }

        return null;
    }

    /**
     * Vrai si la formation cible contient déjà un parcours issu (par copie) du
     * parcours source — pour éviter une recréation en double.
     */
    private function parcoursDejaDansCible(Parcours $parcoursSource, Formation $cible): bool
    {
        foreach ($cible->getParcours() as $p) {
            $current = $p->getParcoursOrigineCopie();
            $guard = 0;
            while ($current !== null && $guard < 20) {
                if ($current->getId() === $parcoursSource->getId()) {
                    return true;
                }
                $current = $current->getParcoursOrigineCopie();
                $guard++;
            }
        }

        return false;
    }

    private function findParcoursCounterpart(Parcours $parcours, Formation $source): ?Parcours
    {
        $current = $parcours->getParcoursOrigineCopie();
        $guard = 0;
        while ($current !== null && $guard < 20) {
            if ($current->getFormation()?->getId() === $source->getId()) {
                return $current;
            }
            $current = $current->getParcoursOrigineCopie();
            $guard++;
        }

        return null;
    }

    /**
     * Détecte les UE et semestres ajoutés/supprimés entre deux structures de
     * parcours. Le service VersioningStructureExtractDiff gère déjà l'ajout/
     * suppression d'EC dans une UE appariée, mais pas le niveau UE ni semestre.
     * Les semestres sont indexés par ordre, les UE par ordre — clés stables
     * d'une année sur l'autre (préservées par la copie).
     *
     * @return array<int, array{kind: string, level: string, label: string}>
     */
    private function detectStructuralAddRemove(StructureParcours $source, StructureParcours $cible): array
    {
        $semLabel = static fn($sem) => 'Semestre ' . ($sem->ordre ?? '?');
        $ueLabel  = static fn($ue) => $ue->ue?->getLibelle() ?? ($ue->display ?: 'UE');

        $changes = [];

        // Semestres ajoutés / supprimés
        foreach ($cible->semestres as $ordre => $sem) {
            if (!array_key_exists($ordre, $source->semestres)) {
                $changes[] = ['kind' => 'added', 'level' => 'Semestre', 'label' => $semLabel($sem)];
            }
        }
        foreach ($source->semestres as $ordre => $sem) {
            if (!array_key_exists($ordre, $cible->semestres)) {
                $changes[] = ['kind' => 'removed', 'level' => 'Semestre', 'label' => $semLabel($sem)];
            }
        }

        // UE ajoutées / supprimées dans les semestres communs
        foreach ($cible->semestres as $ordre => $cibSem) {
            if (!array_key_exists($ordre, $source->semestres)) {
                continue;
            }
            $srcSem = $source->semestres[$ordre];

            foreach ($cibSem->ues as $key => $ue) {
                if (!array_key_exists($key, $srcSem->ues)) {
                    $changes[] = ['kind' => 'added', 'level' => 'UE', 'label' => $semLabel($cibSem) . ' — ' . $ueLabel($ue)];
                }
            }
            foreach ($srcSem->ues as $key => $ue) {
                if (!array_key_exists($key, $cibSem->ues)) {
                    // 'ueId' = id de l'UE source à recréer dans la cible
                    $changes[] = ['kind' => 'removed', 'level' => 'UE', 'label' => $semLabel($srcSem) . ' — ' . $ueLabel($ue), 'ueId' => $ue->ue?->getId()];
                }
            }
        }

        return $changes;
    }

    private function structureHasDiff(VersioningStructureExtractDiff $diff): bool
    {
        foreach ($diff->diffUe as $ues) {
            if (!empty($ues)) {
                return true;
            }
        }
        foreach (($diff->diff['heuresEctsFormation'] ?? []) as $diffObject) {
            if ($diffObject instanceof DiffObject && $diffObject->isDifferent()) {
                return true;
            }
        }

        return false;
    }

    private function canEdit(Formation $formation): bool
    {
        return $this->isGranted('ROLE_ADMIN')
            || $this->isGranted('EDIT', ['route' => 'app_formation', 'subject' => $formation]);
    }

    private function denyUnlessCanEdit(Formation $formation): void
    {
        if (!$this->canEdit($formation)) {
            throw $this->createAccessDeniedException("Vous n'avez pas le droit de modifier cette formation.");
        }
    }
}
