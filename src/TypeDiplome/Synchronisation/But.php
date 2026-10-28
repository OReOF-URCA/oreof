<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Synchronisation/But.php
 * @author davidannebicque
 * @project oreof
 */

declare(strict_types=1);

namespace App\TypeDiplome\Synchronisation;

use App\Entity\ButApprentissageCritique;
use App\Entity\ButCompetence;
use App\Entity\ButNiveau;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Formation;
use App\Entity\Mccc;
use App\Entity\NatureUeEc;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\TypeEc;
use App\Entity\TypeUe;
use App\Entity\Ue;
use App\Enums\EtatDpeEnum;
use App\Referentiel\ModaliteEnseignement;
use App\Referentiel\RegimeInscription;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\NatureUeEcRepository;
use App\Repository\TypeEcRepository;
use App\Repository\TypeUeRepository;
use App\Service\Api\OrebutApiService;
use App\Utils\Tools;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpKernel\KernelInterface;

class But
{
    protected Formation $formation;
    protected bool $type3 = false;
    /** @var array<int|string, Parcours> */
    protected array $listeParcours = [];
    /** @var array<string, ButApprentissageCritique> */
    protected array $listeAcs = [];
    /** @var array<int|string, array<int, array{semestre: Semestre, ues: array<int, Ue>}>> */
    protected array $structure = [];

    private ?TypeEc $typeEcRessource = null;
    private ?TypeEc $typeEcSae = null;
    private ?NatureUeEc $natureEc = null;
    private ?NatureUeEc $natureUe = null;
    private ?TypeUe $typeUe = null;
    private string $baseDir;
    private ?CampagneCollecte $campagneCollecte = null;

    public function __construct(
        TypeEcRepository $typeEcRepository,
        TypeUeRepository $typeUeRepository,
        NatureUeEcRepository $natureUeEcRepository,
        protected EntityManagerInterface $entityManager,
        protected ElementConstitutifRepository $elementConstitutifRepository,
        protected FicheMatiereRepository $ficheMatiereRepository,
        protected OrebutApiService $orebutApiService,
        private KernelInterface $kernel,
    ) {
        $this->typeEcRessource = $typeEcRepository->findOneBy(['libelle' => 'Ressource']);
        $this->typeEcSae = $typeEcRepository->findOneBy(['libelle' => 'SAE']);
        $this->natureEc = $natureUeEcRepository->findOneBy(['libelle' => 'EC obligatoire']);
        $this->natureUe = $natureUeEcRepository->findOneBy(['libelle' => 'UE Obligatoire']);
        $this->typeUe = $typeUeRepository->findOneBy(['libelle' => 'Disciplinaire']);
        $this->baseDir = $this->kernel->getProjectDir() . '/public';
    }

    /**
     * Méthode historique maintenue pour compatibilité ascendante.
     */
    public function synchroniser(Formation $formation, CampagneCollecte $campagneCollecte): bool
    {
        $options = new ButSyncOptions();
        $result = $this->synchroniserWithOptions($formation, $campagneCollecte, $options);

        return $result->success;
    }

    /**
     * Synchronisation sélective et fiable avec l'API ORéBUT.
     */
    public function synchroniserWithOptions(
        Formation $formation,
        ?CampagneCollecte $campagneCollecte,
        ButSyncOptions $options,
    ): ButSyncResult {
        $result = new ButSyncResult();
        $this->formation = $formation;
        $this->campagneCollecte = $campagneCollecte;
        $this->listeParcours = [];
        $this->listeAcs = [];
        $this->structure = [];

        $typeD = $formation->getTypeDiplome();
        if ($typeD === null || strtoupper(trim($typeD->getLibelleCourt())) !== 'BUT') {
            return $result->addError('Cette formation n\'est pas un Bachelor Universitaire de Technologie (B.U.T.).');
        }

        $sigle = $this->resolveSigle($formation, $options->sigleDepartement);
        if ($sigle === '') {
            return $result->addError('Le sigle de la formation est requis pour interroger l\'API ORéBUT.');
        }

        $deptData = $this->orebutApiService->getDepartement($sigle, $formation, $options->annee);
        if ($deptData === null) {
            return $result->addError(sprintf('Spécialité/Département "%s" (version %d) introuvable sur l\'API ORéBUT (%s).', $sigle, $options->annee, $this->orebutApiService->getApiUrl($formation)));
        }

        $this->initTypeStructure($deptData);

        // Index existing ACs for quick lookup
        foreach ($formation->getButCompetences() as $comp) {
            foreach ($comp->getButNiveaux() as $niv) {
                foreach ($niv->getButApprentissageCritiques() as $ac) {
                    if ($ac->getCode()) {
                        $this->listeAcs[$ac->getCode()] = $ac;
                    }
                }
            }
        }

        // 1. Synchronisation des parcours
        if ($options->syncParcours) {
            $this->syncParcours($deptData, $options, $result);
        } else {
            // Index existing parcours and their semestres/UEs in $this->structure
            $this->indexExistingParcoursAndStructure($options->selectedParcoursCodes);
        }

        // 2. Synchronisation du référentiel de compétences et déclinaisons
        if ($options->syncCompetences) {
            $competencesData = $this->orebutApiService->getCompetences($sigle, $formation, $options->annee);
            $this->syncCompetences($competencesData, $result);
            $this->syncDeclinaisonsParcours($deptData, $options, $result);
        }

        // 3. Synchronisation de la maquette (Ressources & SAÉ)
        if ($options->syncMaquette) {
            // Make sure structure is populated
            if (empty($this->structure)) {
                $this->indexExistingParcoursAndStructure($options->selectedParcoursCodes);
            }

            $ressourcesData = $this->orebutApiService->getRessources($sigle, $formation, $options->annee);
            $this->syncRessources($ressourcesData, $result);

            $saesData = $this->orebutApiService->getSaes($sigle, $formation, $options->annee);
            $this->syncSaes($saesData, $result);
        }

        $this->entityManager->flush();
        $result->addMessage($result->getSummary());

        return $result;
    }

    public function resolveSigle(Formation $formation, ?string $sigleDepartement = null): string
    {
        if ($sigleDepartement !== null && trim($sigleDepartement) !== '') {
            return strtolower(trim($sigleDepartement));
        }

        $sigle = (string) $formation->getSigle();
        $sigle = preg_replace('/^(but[\s\-_]+)/i', '', trim($sigle)) ?? $sigle;

        return strtolower(trim($sigle));
    }

    private function initTypeStructure(array $deptData): void
    {
        $typeStructure = strtolower(trim((string) ($deptData['typeStructure'] ?? '')));
        $this->type3 = str_contains($typeStructure, '3');

        $sem = [];
        for ($i = 1; $i <= 6; $i++) {
            $sem[$i] = ($this->type3 || $i >= 3) ? 'parcours' : 'tronc_commun';
        }

        $this->formation->setHasParcours(true);
        $this->formation->setStructureSemestres($sem);
    }

    private function syncParcours(array $deptData, ButSyncOptions $options, ButSyncResult $result): void
    {
        $apcParcours = $deptData['apcParcours'] ?? [];
        if (!is_array($apcParcours)) {
            return;
        }

        // Semestres tronc commun (S1-S2) si pas type 3
        $tronc = [];
        if (!$this->type3) {
            for ($i = 1; $i <= 2; $i++) {
                $tronc[$i] = $this->findOrCreateTroncCommunSemestre($i);
            }
        }

        foreach ($apcParcours as $parcoursData) {
            $code = trim((string) ($parcoursData['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            if (!empty($options->selectedParcoursCodes) && !in_array($code, $options->selectedParcoursCodes, true)) {
                continue;
            }

            $parcours = $this->findExistingParcours($code, (string) ($parcoursData['libelle'] ?? ''));
            $isNew = ($parcours === null);

            if ($parcours === null) {
                $parcours = new Parcours($this->formation);
                $state = $parcours->getEtatSteps();
                $state['3'] = true;
                $parcours->setEtatSteps($state);

                if ($this->campagneCollecte !== null) {
                    $dpe = new DpeParcours();
                    $dpe->setParcours($parcours);
                    $dpe->setFormation($this->formation);
                    $dpe->setCampagneCollecte($this->campagneCollecte);
                    $dpe->setVersion('0.1');
                    $dpe->setEtatValidation([EtatDpeEnum::en_cours_redaction->value => 1]);
                    $parcours->addDpeParcour($dpe);
                    $this->entityManager->persist($dpe);
                }

                $this->formation->addParcour($parcours);
                $this->entityManager->persist($parcours);
                $result->parcoursCreated++;
            } else {
                $result->parcoursUpdated++;
            }

            $parcours->setLibelle((string) ($parcoursData['libelle'] ?? $parcours->getLibelle()));
            $parcours->setSigle($code);
            if (!empty($parcoursData['textePresentation'])) {
                $parcours->setContenuFormation((string) $parcoursData['textePresentation']);
            }

            $idKey = (int) ($parcoursData['id'] ?? 0);
            $this->listeParcours[$idKey > 0 ? $idKey : $code] = $parcours;
            $this->structure[$idKey > 0 ? $idKey : $code] = [];

            // Rattachement semestres 1..6
            $debut = $this->type3 ? 1 : 3;
            if (!$this->type3) {
                for ($i = 1; $i <= 2; $i++) {
                    $this->ensureSemestreParcours($tronc[$i], $parcours, $i);
                    $this->structure[$idKey > 0 ? $idKey : $code][$i] = [
                        'semestre' => $tronc[$i],
                        'ues' => $this->indexUesForSemestre($tronc[$i]),
                    ];
                }
            }

            for ($i = $debut; $i <= 6; $i++) {
                $semestre = $this->findOrCreateParcoursSemestre($parcours, $i);
                $this->structure[$idKey > 0 ? $idKey : $code][$i] = [
                    'semestre' => $semestre,
                    'ues' => $this->indexUesForSemestre($semestre),
                ];
            }
        }
    }

    private function findExistingParcours(string $code, string $libelle): ?Parcours
    {
        foreach ($this->formation->getParcours() as $p) {
            if (strcasecmp((string) $p->getSigle(), $code) === 0) {
                return $p;
            }
            if ($libelle !== '' && strcasecmp((string) $p->getLibelle(), $libelle) === 0) {
                return $p;
            }
        }

        return null;
    }

    private function findOrCreateTroncCommunSemestre(int $ordre): Semestre
    {
        foreach ($this->formation->getParcours() as $parcours) {
            foreach ($parcours->getSemestreParcours() as $sp) {
                $s = $sp->getSemestre();
                if ($s !== null && $s->isTroncCommun() && $s->getOrdre() === $ordre) {
                    return $s;
                }
            }
        }

        $semestre = new Semestre();
        $semestre->setOrdre($ordre);
        $semestre->setTroncCommun(true);
        $this->entityManager->persist($semestre);

        return $semestre;
    }

    private function findOrCreateParcoursSemestre(Parcours $parcours, int $ordre): Semestre
    {
        foreach ($parcours->getSemestreParcours() as $sp) {
            $s = $sp->getSemestre();
            if ($s !== null && !$s->isTroncCommun() && $sp->getOrdre() === $ordre) {
                return $s;
            }
        }

        $semestre = new Semestre();
        $semestre->setOrdre($ordre);
        $semestre->setTroncCommun(false);
        $this->entityManager->persist($semestre);

        $sp = new SemestreParcours($semestre, $parcours);
        $sp->setOrdre($ordre);
        $sp->setPorteur(true);
        $this->entityManager->persist($sp);
        $semestre->addSemestreParcour($sp);

        return $semestre;
    }

    private function ensureSemestreParcours(Semestre $semestre, Parcours $parcours, int $ordre): void
    {
        foreach ($parcours->getSemestreParcours() as $sp) {
            if ($sp->getSemestre() === $semestre && $sp->getOrdre() === $ordre) {
                return;
            }
        }

        $sp = new SemestreParcours($semestre, $parcours);
        $sp->setOrdre($ordre);
        $sp->setPorteur(true);
        $this->entityManager->persist($sp);
        $semestre->addSemestreParcour($sp);
    }

    /**
     * @return array<int, Ue>
     */
    private function indexUesForSemestre(Semestre $semestre): array
    {
        $ues = [];
        foreach ($semestre->getUes() as $ue) {
            if ($ue->getOrdre() !== null) {
                $ues[$ue->getOrdre()] = $ue;
            }
        }

        return $ues;
    }

    /**
     * @param array<int, string>|null $selectedCodes
     */
    private function indexExistingParcoursAndStructure(?array $selectedCodes = null): void
    {
        foreach ($this->formation->getParcours() as $parcours) {
            $sigle = (string) $parcours->getSigle();
            if ($selectedCodes !== null && !empty($selectedCodes) && !in_array($sigle, $selectedCodes, true)) {
                continue;
            }

            $key = $parcours->getId() ?? $parcours->getSigle() ?? uniqid('p_', true);
            $this->listeParcours[$key] = $parcours;
            $this->structure[$key] = [];

            foreach ($parcours->getSemestreParcours() as $sp) {
                $s = $sp->getSemestre();
                if ($s !== null && $sp->getOrdre() !== null) {
                    $this->structure[$key][$sp->getOrdre()] = [
                        'semestre' => $s,
                        'ues' => $this->indexUesForSemestre($s),
                    ];
                }
            }
        }
    }

    private function syncCompetences(array $competencesData, ButSyncResult $result): void
    {
        foreach ($competencesData as $compData) {
            $numero = (int) ($compData['numero'] ?? 0);
            $nomCourt = (string) ($compData['nom_court'] ?? '');
            if ($numero <= 0 && $nomCourt === '') {
                continue;
            }

            $comp = $this->findExistingCompetence($numero, $nomCourt);
            if ($comp === null) {
                $comp = new ButCompetence();
                $comp->setFormation($this->formation);
                if ($this->campagneCollecte !== null) {
                    $comp->setCampagneCollecte($this->campagneCollecte);
                }
                $this->formation->addButCompetence($comp);
            }

            $comp->setNumero($numero);
            $comp->setNomCourt($nomCourt);
            $comp->setLibelle((string) ($compData['libelle'] ?? ''));

            $tSituations = [];
            foreach ($compData['apcSituationProfessionnelles'] ?? [] as $sit) {
                if (!empty($sit['libelle'])) {
                    $tSituations[] = (string) $sit['libelle'];
                }
            }
            $comp->setSituations($tSituations);

            $tComposantes = [];
            foreach ($compData['apcComposanteEssentielles'] ?? [] as $ce) {
                if (!empty($ce['libelle'])) {
                    $tComposantes[] = (string) $ce['libelle'];
                }
            }
            $comp->setComposantes($tComposantes);
            $this->entityManager->persist($comp);
            $result->competencesCount++;

            // Niveaux
            foreach ($compData['apcNiveaux'] ?? [] as $nivData) {
                $ordreNiv = (int) ($nivData['ordre'] ?? 0);
                $niveau = $this->findExistingNiveau($comp, $ordreNiv);
                if ($niveau === null) {
                    $niveau = new ButNiveau();
                    $niveau->setCompetence($comp);
                    $comp->addButNiveau($niveau);
                }

                $niveau->setOrdre($ordreNiv);
                $niveau->setLibelle((string) ($nivData['libelle'] ?? ''));
                $niveau->setAnnee((string) ($nivData['annee']['libelle'] ?? ('BUT' . $ordreNiv)));
                $this->entityManager->persist($niveau);
                $result->niveauxCount++;

                // Apprentissages critiques
                foreach ($nivData['apcApprentissageCritiques'] ?? [] as $acData) {
                    $code = trim((string) ($acData['code'] ?? ''));
                    if ($code === '') {
                        continue;
                    }

                    $ac = $this->findExistingAc($niveau, $code);
                    if ($ac === null) {
                        $ac = new ButApprentissageCritique();
                        $ac->setNiveau($niveau);
                        $niveau->addButApprentissageCritique($ac);
                    }

                    $ac->setCode($code);
                    $ac->setLibelle((string) ($acData['libelle'] ?? ''));
                    $this->entityManager->persist($ac);
                    $this->listeAcs[$code] = $ac;
                    $result->acsCount++;
                }
            }
        }
    }

    private function findExistingCompetence(int $numero, string $nomCourt): ?ButCompetence
    {
        foreach ($this->formation->getButCompetences() as $comp) {
            if ($numero > 0 && $comp->getNumero() === $numero) {
                return $comp;
            }
            if ($nomCourt !== '' && strcasecmp((string) $comp->getNomCourt(), $nomCourt) === 0) {
                return $comp;
            }
        }

        return null;
    }

    private function findExistingNiveau(ButCompetence $comp, int $ordre): ?ButNiveau
    {
        foreach ($comp->getButNiveaux() as $niv) {
            if ($niv->getOrdre() === $ordre) {
                return $niv;
            }
        }

        return null;
    }

    private function findExistingAc(ButNiveau $niveau, string $code): ?ButApprentissageCritique
    {
        foreach ($niveau->getButApprentissageCritiques() as $ac) {
            if (strcasecmp((string) $ac->getCode(), $code) === 0) {
                return $ac;
            }
        }

        return null;
    }

    private function syncDeclinaisonsParcours(array $deptData, ButSyncOptions $options, ButSyncResult $result): void
    {
        $apcParcours = $deptData['apcParcours'] ?? [];
        if (!is_array($apcParcours)) {
            return;
        }

        foreach ($apcParcours as $parcData) {
            $code = trim((string) ($parcData['code'] ?? ''));
            if (!empty($options->selectedParcoursCodes) && !in_array($code, $options->selectedParcoursCodes, true)) {
                continue;
            }

            $idKey = (int) ($parcData['id'] ?? 0);
            $parcoursKey = $idKey > 0 ? $idKey : $code;
            if (!isset($this->structure[$parcoursKey])) {
                // Find matching parcours in structure by sigle
                foreach ($this->listeParcours as $k => $p) {
                    if (strcasecmp((string) $p->getSigle(), $code) === 0) {
                        $parcoursKey = $k;
                        break;
                    }
                }
            }

            if (!isset($this->structure[$parcoursKey])) {
                continue;
            }

            $apcParcoursNiveaux = $parcData['apcParcoursNiveaux'] ?? [];
            foreach ($apcParcoursNiveaux as $apcParcoursNiveau) {
                $annee = (string) ($apcParcoursNiveau['niveau']['annee']['libelle'] ?? '');
                $offset = match ($annee) {
                    'BUT2' => 2,
                    'BUT3' => 4,
                    default => 0,
                };

                $compData = $apcParcoursNiveau['niveau']['competence'] ?? null;
                if ($compData === null) {
                    continue;
                }

                $compId = (int) ($compData['id'] ?? $compData['numero'] ?? 0);
                $compNomCourt = (string) ($compData['nom_court'] ?? ('UE ' . $compId));
                $compNumero = (int) ($compData['numero'] ?? $compId);

                for ($j = 1; $j <= 2; $j++) {
                    $semestreOrdre = $offset + $j;
                    if (!isset($this->structure[$parcoursKey][$semestreOrdre])) {
                        continue;
                    }

                    $semestre = $this->structure[$parcoursKey][$semestreOrdre]['semestre'];
                    $existingUe = $this->findExistingUe($semestre, $compNumero, $compNomCourt);

                    if ($existingUe === null) {
                        $ue = new Ue();
                        $ue->setTypeUe($this->typeUe);
                        $ue->setNatureUeEc($this->natureUe);
                        $ue->setOrdre($compNumero);
                        $ue->setLibelle($compNomCourt);
                        $ue->setSemestre($semestre);
                        $this->entityManager->persist($ue);
                        $this->structure[$parcoursKey][$semestreOrdre]['ues'][$compId] = $ue;
                        $this->structure[$parcoursKey][$semestreOrdre]['ues'][$compNumero] = $ue;
                        $result->uesCreated++;
                    } else {
                        $this->structure[$parcoursKey][$semestreOrdre]['ues'][$compId] = $existingUe;
                        $this->structure[$parcoursKey][$semestreOrdre]['ues'][$compNumero] = $existingUe;
                    }
                }
            }
        }
    }

    private function findExistingUe(Semestre $semestre, int $ordre, string $libelle): ?Ue
    {
        foreach ($semestre->getUes() as $ue) {
            if ($ue->getOrdre() === $ordre) {
                return $ue;
            }
            if ($libelle !== '' && strcasecmp((string) $ue->getLibelle(), $libelle) === 0) {
                return $ue;
            }
        }

        return null;
    }

    private function syncRessources(array $ressourcesData, ButSyncResult $result): void
    {
        foreach ($ressourcesData as $res) {
            $codeMatiere = trim((string) ($res['codeMatiere'] ?? ''));
            if ($codeMatiere === '') {
                continue;
            }

            $fm = $this->findExistingFicheMatiere($codeMatiere, FicheMatiere::TYPE_MATIERE_RESSOURCE);
            if ($fm === null) {
                $fm = new FicheMatiere();
                $fm->setTypeMatiere(FicheMatiere::TYPE_MATIERE_RESSOURCE);
                $fm->setSigle($codeMatiere);
            }

            $fm->setLibelle((string) ($res['libelle'] ?? $codeMatiere));
            $fm->setDescription((string) ($res['description'] ?? ''));
            $fm->addLangueDispense('fr');
            $fm->addLangueSupport('fr');
            if ($this->campagneCollecte !== null) {
                $fm->setCampagneCollecte($this->campagneCollecte);
            }

            // Volumes PPN
            $fm->setVolumeCmPresentiel(Tools::convertToFloat($res['cmPpn'] ?? 0));
            $fm->setVolumeTdPresentiel(Tools::convertToFloat($res['tdPpn'] ?? 0));
            $fm->setVolumeTpPresentiel(Tools::convertToFloat($res['tpPpn'] ?? 0));
            $fm->setVolumeTe(Tools::convertToFloat($res['heuresTotales'] ?? 0));
            $this->entityManager->persist($fm);
            $result->ressourcesCount++;

            $semestreOrdre = (int) ($res['semestre']['ordreLmd'] ?? 0);
            $concernedParcoursKeys = $this->resolveConcernedParcoursKeys($res['apcRessourceParcours'] ?? [], $semestreOrdre);

            $this->linkFicheToParcours($fm, $concernedParcoursKeys);

            // Génération ECs dans les UEs correspondantes
            $this->genereEcRessourceByUes($res, $fm, $concernedParcoursKeys, $semestreOrdre, $result);
        }
    }

    private function syncSaes(array $saesData, ButSyncResult $result): void
    {
        foreach ($saesData as $sae) {
            $codeMatiere = trim((string) ($sae['codeMatiere'] ?? ''));
            if ($codeMatiere === '') {
                continue;
            }

            $fm = $this->findExistingFicheMatiere($codeMatiere, FicheMatiere::TYPE_MATIERE_SAE);
            if ($fm === null) {
                $fm = new FicheMatiere();
                $fm->setTypeMatiere(FicheMatiere::TYPE_MATIERE_SAE);
                $fm->setSigle($codeMatiere);
            }

            $fm->setLibelle((string) ($sae['libelle'] ?? $codeMatiere));
            $fm->setDescription((string) ($sae['description'] ?? ''));
            $fm->setObjectifs((string) ($sae['objectifs'] ?? ''));
            $fm->addLangueDispense('fr');
            $fm->addLangueSupport('fr');
            if ($this->campagneCollecte !== null) {
                $fm->setCampagneCollecte($this->campagneCollecte);
            }
            $fm->setVolumeTe(Tools::convertToFloat($sae['heuresTotales'] ?? 0));
            $this->entityManager->persist($fm);
            $result->saesCount++;

            $semestreOrdre = (int) ($sae['semestre']['ordreLmd'] ?? 0);
            $concernedParcoursKeys = $this->resolveConcernedParcoursKeys($sae['apcSaeParcours'] ?? [], $semestreOrdre);

            $this->linkFicheToParcours($fm, $concernedParcoursKeys);

            // Génération ECs dans les UEs correspondantes
            $this->genereEcSaeByUes($sae, $fm, $concernedParcoursKeys, $semestreOrdre, $result);
        }
    }

    private function findExistingFicheMatiere(string $sigle, string $typeMatiere): ?FicheMatiere
    {
        if ($this->campagneCollecte !== null) {
            $fm = $this->ficheMatiereRepository->findOneBy([
                'sigle' => $sigle,
                'campagneCollecte' => $this->campagneCollecte,
                'typeMatiere' => $typeMatiere,
            ]);
            if ($fm !== null) {
                return $fm;
            }
        }

        // Check across parcours of this formation
        foreach ($this->formation->getParcours() as $p) {
            foreach ($p->getFicheMatieres() as $f) {
                if ($f->getTypeMatiere() === $typeMatiere && strcasecmp((string) $f->getSigle(), $sigle) === 0) {
                    return $f;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int|string>
     */
    private function resolveConcernedParcoursKeys(array $apcParcoursLinks, int $semestreOrdre): array
    {
        $keys = [];
        if (empty($apcParcoursLinks)) {
            // Tous les parcours de la formation
            return array_keys($this->listeParcours);
        }

        foreach ($apcParcoursLinks as $link) {
            $pData = $link['parcours'] ?? null;
            if ($pData === null) {
                continue;
            }
            $pId = (int) ($pData['id'] ?? 0);
            $pCode = (string) ($pData['code'] ?? '');

            if (isset($this->listeParcours[$pId])) {
                $keys[] = $pId;
            } else {
                foreach ($this->listeParcours as $k => $parcours) {
                    if (strcasecmp((string) $parcours->getSigle(), $pCode) === 0) {
                        $keys[] = $k;
                        break;
                    }
                }
            }
        }

        return !empty($keys) ? array_unique($keys) : array_keys($this->listeParcours);
    }

    /**
     * @param array<int|string> $concernedParcoursKeys
     */
    private function linkFicheToParcours(FicheMatiere $fm, array $concernedParcoursKeys): void
    {
        if (empty($concernedParcoursKeys) || empty($this->listeParcours)) {
            return;
        }

        $firstKey = reset($concernedParcoursKeys);
        if ($fm->getParcours() === null && isset($this->listeParcours[$firstKey])) {
            $fm->setParcours($this->listeParcours[$firstKey]);
        }

        if (count($concernedParcoursKeys) > 1) {
            $fm->setEnseignementMutualise(true);
            foreach ($concernedParcoursKeys as $key) {
                if ($key === $firstKey || !isset($this->listeParcours[$key])) {
                    continue;
                }
                $parcours = $this->listeParcours[$key];
                if (!$this->hasFicheMatiereMutualisable($fm, $parcours)) {
                    $mut = new FicheMatiereMutualisable();
                    $mut->setFicheMatiere($fm);
                    $mut->setParcours($parcours);
                    $this->entityManager->persist($mut);
                }
            }
        }
    }

    private function hasFicheMatiereMutualisable(FicheMatiere $fm, Parcours $parcours): bool
    {
        foreach ($fm->getFicheMatiereParcours() as $fp) {
            if ($fp->getParcours() === $parcours) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int|string> $concernedParcoursKeys
     */
    private function genereEcRessourceByUes(
        array $ressource,
        FicheMatiere $fm,
        array $concernedParcoursKeys,
        int $semestreOrdre,
        ButSyncResult $result,
    ): void {
        $competenceLinks = $ressource['apcRessourceCompetences'] ?? [];
        foreach ($competenceLinks as $compLink) {
            $compData = $compLink['competence'] ?? null;
            if ($compData === null) {
                continue;
            }
            $compId = (int) ($compData['id'] ?? $compData['numero'] ?? 0);
            $coeff = Tools::convertToFloat($compLink['coefficient'] ?? 0);

            foreach ($concernedParcoursKeys as $parcoursKey) {
                $ue = $this->structure[$parcoursKey][$semestreOrdre]['ues'][$compId] ?? null;
                if ($ue === null) {
                    continue;
                }

                $parcours = $this->listeParcours[$parcoursKey] ?? null;
                $ec = $this->findExistingEc($ue, $fm, $parcours);
                if ($ec === null) {
                    $ec = new ElementConstitutif();
                    $ec->setModaliteEnseignementValue(ModaliteEnseignement::PRESENTIELLE);
                    $ec->setNatureUeEc($this->natureEc);
                    $ec->setTypeEc($this->typeEcRessource);
                    $ec->setUe($ue);
                    $ec->setFicheMatiere($fm);
                    if ($parcours !== null) {
                        $ec->setParcours($parcours);
                    }
                    $this->entityManager->persist($ec);
                    $result->ecsCount++;
                }

                $ec->setOrdre((int) ($ressource['ordre'] ?? 1));
                $ec->setLibelle((string) ($ressource['libelle'] ?? ''));
                $ec->setEcts($coeff);
                $ec->genereCode();
            }

            // Rattachement des apprentissages critiques
            foreach ($ressource['apcRessourceApprentissageCritiques'] ?? [] as $acLink) {
                $codeAc = (string) ($acLink['apprentissageCritique']['code'] ?? '');
                if ($codeAc !== '' && isset($this->listeAcs[$codeAc])) {
                    $fm->addApprentissagesCritique($this->listeAcs[$codeAc]);
                }
            }
        }
    }

    /**
     * @param array<int|string> $concernedParcoursKeys
     */
    private function genereEcSaeByUes(
        array $sae,
        FicheMatiere $fm,
        array $concernedParcoursKeys,
        int $semestreOrdre,
        ButSyncResult $result,
    ): void {
        $competenceLinks = $sae['apcSaeCompetences'] ?? [];
        foreach ($competenceLinks as $compLink) {
            $compData = $compLink['competence'] ?? null;
            if ($compData === null) {
                continue;
            }
            $compId = (int) ($compData['id'] ?? $compData['numero'] ?? 0);
            $coeff = Tools::convertToFloat($compLink['coefficient'] ?? 0);

            foreach ($concernedParcoursKeys as $parcoursKey) {
                $ue = $this->structure[$parcoursKey][$semestreOrdre]['ues'][$compId] ?? null;
                if ($ue === null) {
                    continue;
                }

                $parcours = $this->listeParcours[$parcoursKey] ?? null;
                $ec = $this->findExistingEc($ue, $fm, $parcours);
                if ($ec === null) {
                    $ec = new ElementConstitutif();
                    $ec->setModaliteEnseignementValue(ModaliteEnseignement::PRESENTIELLE);
                    $ec->setNatureUeEc($this->natureEc);
                    $ec->setTypeEc($this->typeEcSae);
                    $ec->setUe($ue);
                    $ec->setFicheMatiere($fm);
                    if ($parcours !== null) {
                        $ec->setParcours($parcours);
                    }
                    $this->entityManager->persist($ec);
                    $result->ecsCount++;
                }

                $rawOrdre = (int) ($sae['ordre'] ?? 1);
                $ec->setOrdre($rawOrdre === 99 ? 60 : ($rawOrdre + 50));
                $ec->setLibelle((string) ($sae['libelle'] ?? ''));
                $ec->setEcts($coeff);
                $ec->genereCode();
            }

            // Rattachement des apprentissages critiques
            foreach ($sae['apcSaeApprentissageCritiques'] ?? [] as $acLink) {
                $codeAc = (string) ($acLink['apprentissageCritique']['code'] ?? '');
                if ($codeAc !== '' && isset($this->listeAcs[$codeAc])) {
                    $fm->addApprentissagesCritique($this->listeAcs[$codeAc]);
                }
            }
        }
    }

    private function findExistingEc(Ue $ue, FicheMatiere $fm, ?Parcours $parcours): ?ElementConstitutif
    {
        foreach ($ue->getElementConstitutifs() as $ec) {
            if ($ec->getFicheMatiere() === $fm && ($parcours === null || $ec->getParcours() === $parcours)) {
                return $ec;
            }
        }

        return null;
    }

    public function synchroniserMccc(Formation $formation): void
    {
        $fifc = 'fi';
        foreach ($formation->getRegimeInscriptionValues() as $regime) {
            if ($regime === RegimeInscription::FI || $regime === RegimeInscription::FC || $regime === 'fi' || $regime === 'fc') {
                $fifc = 'fi';
            } else {
                $fifc = 'alt';
            }
        }
        $sigle = strtoupper((string) $formation->getSigle());
        $spreadsheet = IOFactory::load($this->baseDir . '/but/MCCC_' . $fifc . '_' . $sigle . ' 2023-2024.xlsx');

        $ligneDebut = 22;

        $ecs = $this->elementConstitutifRepository->findByFormation($formation);
        $tabEcs = [];
        foreach ($ecs as $ec) {
            $fiche = $ec->getFicheMatiere();
            if ($fiche !== null && $fiche->getSigle() !== null) {
                $tabEcs[$fiche->getSigle()] = $fiche;
            }
            foreach ($ec->getMcccs() as $mccc) {
                $this->entityManager->remove($mccc);
            }
        }

        for ($i = 1; $i <= 6; $i++) {
            if ($spreadsheet->sheetNameExists('Semestre ' . $i)) {
                $sheet = $spreadsheet->getSheetByName('Semestre ' . $i);
                if ($sheet !== null) {
                    $ligne = $ligneDebut;
                    while ($sheet->getCell('H' . $ligne)->getValue() !== 'Date du vote de la CFVU :' && $ligne < 100) {
                        $codeEc = $sheet->getCell('B' . $ligne)->getValue();
                        if (array_key_exists($codeEc, $tabEcs)) {
                            $ec = $tabEcs[$codeEc];
                            $ec->setVolumeCmPresentiel(Tools::convertToFloat($sheet->getCell('G' . $ligne)->getValue()));
                            $ec->setVolumeTdPresentiel(Tools::convertToFloat($sheet->getCell('H' . $ligne)->getValue()));
                            $ec->setVolumeTpPresentiel(Tools::convertToFloat($sheet->getCell('I' . $ligne)->getValue()));
                            $ec->setVolumeTe(Tools::convertToFloat($sheet->getCell('J' . $ligne)->getValue()));

                            $tabMcccs = [
                                'N' => ['td_tp_oral', 'O'],
                                'P' => ['td_tp_ecrit', 'Q'],
                                'R' => ['td_tp_rapport', 'S'],
                                'T' => ['td_tp_autre', 'U'],
                                'V' => ['cm_ecrit', 'W'],
                                'X' => ['cm_rapport', 'Y'],
                                'Z' => ['iut_portfolio', 'AA'],
                                'AB' => ['iut_livrable', 'AC'],
                                'AD' => ['iut_rapport', 'AE'],
                                'AF' => ['iut_soutenance', 'AG'],
                                'AH' => ['hors_iut_entreprise', 'AI'],
                                'AJ' => ['hors_iut_rapport', 'AK'],
                                'AL' => ['hors_iut_soutenance', 'AM'],
                            ];

                            foreach ($ec->getMcccs() as $mccc) {
                                $this->entityManager->remove($mccc);
                            }
                            $totalPourcentage = 0;
                            foreach ($tabMcccs as $key => $value) {
                                if ($sheet->getCell($key . $ligne)->getValue() !== '') {
                                    $mccc = new Mccc();
                                    $mccc->setTypeEpreuve([$value[0]]);
                                    $mccc->setPourcentage(Tools::convertToFloat($sheet->getCell($key . $ligne)->getValue()) * 100);
                                    $mccc->setNbEpreuves((int) $sheet->getCell($value[1] . $ligne)->getValue());
                                    $mccc->setLibelle($value[0]);
                                    $mccc->setControleContinu(true);
                                    $mccc->setNumeroSession(1);
                                    $mccc->setExamenTerminal(false);
                                    $totalPourcentage += $mccc->getPourcentage();
                                    $this->entityManager->persist($mccc);
                                    $ec->addMccc($mccc);
                                }
                            }

                            if ($totalPourcentage === 100.0) {
                                $ec->setEtatMccc('Complet');
                            } else {
                                $ec->setEtatMccc(null);
                            }
                        }
                        $ligne++;
                    }
                    $this->entityManager->flush();
                }
            }
        }
    }
}
