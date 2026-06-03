<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Repository\FormationRepository;
use App\Service\FormationDiffBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

        foreach ($cibles as $formationCible) {
            foreach ($keys as $key) {
                $this->diffBuilder->applyField($formationCible, $source, $key);
            }
        }
        $this->em->flush();

        $nbYears = count($cibles);
        $this->toast('success', sprintf(
            '%d champ%s récupéré%s depuis %s%s.',
            count($keys),
            count($keys) > 1 ? 's' : '',
            count($keys) > 1 ? 's' : '',
            $this->diffBuilder->anneeLabel($source),
            $nbYears > 1 ? sprintf(' sur %d années', $nbYears) : ''
        ));

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

        return $this->render('admin/formation_comparaison/diff.html.twig', [
            'cible'            => $target,
            'source'          => $source,
            'cibleLabel'      => $this->diffBuilder->anneeLabel($target),
            'sourceLabel'     => $this->diffBuilder->anneeLabel($source),
            'targetOptions'   => $targetOptions,
            'sourceOptions'   => $sourceOptions,
            'broadcastYears'  => $broadcastYears,
            'differentFields' => $differentFields,
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
