<?php

declare(strict_types=1);

namespace App\Service\Parcours;

use App\DTO\Comparison\AnomalyItem;
use App\DTO\Comparison\EcDiff;
use App\DTO\Comparison\FieldDiff;
use App\DTO\Comparison\FicheMatiereDiff;
use App\DTO\Comparison\MutualisationDiff;
use App\DTO\Comparison\ParcoursN1ComparisonResult;
use App\DTO\Comparison\SemestreDiff;
use App\DTO\Comparison\TotauxDiff;
use App\DTO\Comparison\UeDiff;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\Ue;

class ParcoursN1ComparatorService
{
    /**
     * Résout le parcours d'origine N-1 d'un parcours N.
     */
    public function resolveParcoursN1(Parcours $parcoursN): ?Parcours
    {
        $n1 = $parcoursN->getParcoursOrigineCopie();
        if ($n1 !== null) {
            return $n1;
        }

        $formationN1 = $parcoursN->getFormation()?->getFormationOrigineCopie();
        if ($formationN1 !== null) {
            foreach ($formationN1->getParcours() as $cand) {
                if ($parcoursN->getSigle() !== null && strcasecmp((string) $cand->getSigle(), (string) $parcoursN->getSigle()) === 0) {
                    return $cand;
                }
                if ($parcoursN->getLibelle() !== null && strcasecmp((string) $cand->getLibelle(), (string) $parcoursN->getLibelle()) === 0) {
                    return $cand;
                }
            }
        }

        return null;
    }

    /**
     * Compare le parcours N avec son équivalent N-1.
     */
    public function compare(Parcours $parcoursN, ?Parcours $parcoursN1 = null, string $scope = 'parcours'): ParcoursN1ComparisonResult
    {
        $isParcoursOriginDirect = true;
        if ($parcoursN1 === null) {
            $isParcoursOriginDirect = $parcoursN->getParcoursOrigineCopie() !== null;
            $parcoursN1 = $this->resolveParcoursN1($parcoursN);
        }

        if ($parcoursN1 === null) {
            $fichesParcoursN = $this->collectFiches($parcoursN, 'parcours');
            $fichesFormationN = $this->collectFiches($parcoursN, 'formation');

            return new ParcoursN1ComparisonResult(
                parcoursN: $parcoursN,
                parcoursN1: null,
                anomalies: [
                    new AnomalyItem(
                        severity: AnomalyItem::SEVERITY_ERROR,
                        category: 'structure',
                        message: sprintf('Aucun parcours N-1 correspondant trouvé pour le parcours #%d (%s).', (int) $parcoursN->getId(), (string) $parcoursN->getLibelle())
                    ),
                ],
                isParcoursOriginDirect: false,
                scope: $scope,
                totalFichesParcours: count($fichesParcoursN),
                totalFichesFormation: count($fichesFormationN),
            );
        }

        $anomalies = [];

        // Compter les fiches totales parcours vs formation
        $fichesParcoursN = $this->collectFiches($parcoursN, 'parcours');
        $fichesFormationN = $this->collectFiches($parcoursN, 'formation');
        $totalFichesParcours = count($fichesParcoursN);
        $totalFichesFormation = count($fichesFormationN);

        // 1. Comparaison des fiches matières
        $fichesDiff = $this->compareFichesMatieres($parcoursN, $parcoursN1, $anomalies, $scope);

        // 2. Comparaison de la maquette (Semestres, UEs, ECs)
        $semestresDiff = $this->compareStructure($parcoursN, $parcoursN1, $anomalies);

        // 3. Comparaison des mutualisations
        $mutualisationsDiff = $this->compareMutualisations($parcoursN, $parcoursN1, $anomalies);

        // 4. Calcul des totaux globaux
        $totauxGlobal = $this->calculateTotauxGlobal($semestresDiff);

        if (abs($totauxGlobal->diffEcts) > 0.001) {
            $anomalies[] = new AnomalyItem(
                severity: AnomalyItem::SEVERITY_WARNING,
                category: 'volume',
                message: sprintf(
                    'Écart de crédits ECTS globaux sur le parcours : N-1 = %.1f ECTS, N = %.1f ECTS (écart : %+.1f).',
                    $totauxGlobal->ectsN1,
                    $totauxGlobal->ectsN,
                    $totauxGlobal->diffEcts
                )
            );
        }

        if (abs($totauxGlobal->diffTotalPres) > 0.001) {
            $anomalies[] = new AnomalyItem(
                severity: AnomalyItem::SEVERITY_WARNING,
                category: 'volume',
                message: sprintf(
                    'Écart d\'heures présentielles globales : N-1 = %.1fh, N = %.1fh (écart : %+.1fh).',
                    $totauxGlobal->totalPresN1,
                    $totauxGlobal->totalPresN,
                    $totauxGlobal->diffTotalPres
                )
            );
        }

        return new ParcoursN1ComparisonResult(
            parcoursN: $parcoursN,
            parcoursN1: $parcoursN1,
            fiches: $fichesDiff,
            semestres: $semestresDiff,
            mutualisations: $mutualisationsDiff,
            totauxGlobal: $totauxGlobal,
            anomalies: $anomalies,
            isParcoursOriginDirect: $isParcoursOriginDirect,
            scope: $scope,
            totalFichesParcours: $totalFichesParcours,
            totalFichesFormation: $totalFichesFormation,
        );
    }

    /**
     * Collecte toutes les fiches matières pertinentes pour un parcours ou sa formation.
     *
     * @return array<string, array{fiche: FicheMatiere, source: string}>
     */
    public function collectFiches(Parcours $parcours, string $scope = 'parcours'): array
    {
        /** @var array<string, array{fiche: FicheMatiere, source: string}> $fiches */
        $fiches = [];

        // 1. Fiches directement portées par le parcours
        foreach ($parcours->getFicheMatieres() as $fm) {
            $key = $this->getFicheUniqueKey($fm);
            $fiches[$key] = ['fiche' => $fm, 'source' => 'portee'];
        }

        // 2. Fiches mutualisées avec ce parcours
        foreach ($parcours->getFicheMatiereParcours() as $fmp) {
            $fm = $fmp->getFicheMatiere();
            if ($fm !== null) {
                $key = $this->getFicheUniqueKey($fm);
                if (!isset($fiches[$key])) {
                    $fiches[$key] = ['fiche' => $fm, 'source' => 'mutualisee'];
                }
            }
        }

        // 3. Fiches rattachées aux ECs de tous les semestres du parcours (tronc commun S1-S2 et spécialité)
        foreach ($parcours->getSemestreParcours() as $sp) {
            $sem = $sp->getSemestre();
            if ($sem === null) {
                continue;
            }
            foreach ($sem->getUes() as $ue) {
                foreach ($ue->getElementConstitutifs() as $ec) {
                    if ($ec->getParcours() === null || $ec->getParcours() === $parcours) {
                        $fm = $ec->getFicheMatiere();
                        if ($fm !== null) {
                            $key = $this->getFicheUniqueKey($fm);
                            if (!isset($fiches[$key])) {
                                $fiches[$key] = ['fiche' => $fm, 'source' => 'maquette'];
                            }
                        }
                        foreach ($ec->getEcEnfants() as $ecEnfant) {
                            $fmEnfant = $ecEnfant->getFicheMatiere();
                            if ($fmEnfant !== null) {
                                $key = $this->getFicheUniqueKey($fmEnfant);
                                if (!isset($fiches[$key])) {
                                    $fiches[$key] = ['fiche' => $fmEnfant, 'source' => 'maquette'];
                                }
                            }
                        }
                    }
                }
            }
        }

        // 4. Si scope == 'formation', inclure également toutes les fiches de tous les parcours de la formation
        if ($scope === 'formation' && $parcours->getFormation() !== null) {
            foreach ($parcours->getFormation()->getParcours() as $otherP) {
                foreach ($otherP->getFicheMatieres() as $fm) {
                    $key = $this->getFicheUniqueKey($fm);
                    if (!isset($fiches[$key])) {
                        $fiches[$key] = ['fiche' => $fm, 'source' => 'formation'];
                    }
                }
                foreach ($otherP->getFicheMatiereParcours() as $fmp) {
                    $fm = $fmp->getFicheMatiere();
                    if ($fm !== null) {
                        $key = $this->getFicheUniqueKey($fm);
                        if (!isset($fiches[$key])) {
                            $fiches[$key] = ['fiche' => $fm, 'source' => 'formation'];
                        }
                    }
                }
            }
        }

        return $fiches;
    }

    private function getFicheUniqueKey(FicheMatiere $fm): string
    {
        if ($fm->getId() !== null) {
            return 'id_' . $fm->getId();
        }

        $sigle = strtoupper(trim((string) $fm->getSigle()));
        $type = (string) $fm->getTypeMatiere();
        if ($sigle !== '' && $type !== '') {
            return 'code_' . $type . '_' . $sigle;
        }

        return 'obj_' . spl_object_id($fm);
    }

    /**
     * @param list<AnomalyItem> $anomalies
     * @return list<FicheMatiereDiff>
     */
    private function compareFichesMatieres(Parcours $parcoursN, Parcours $parcoursN1, array &$anomalies, string $scope): array
    {
        /** @var list<FicheMatiereDiff> $result */
        $result = [];

        // 1. Collecter les fiches selon le périmètre (parcours ou formation)
        $itemsN1 = $this->collectFiches($parcoursN1, $scope);
        $itemsN = $this->collectFiches($parcoursN, $scope);

        // 2. Collecter également toutes les fiches de la formation N pour détecter si une fiche
        // existe en N dans la formation mais a été détachée du parcours
        $allFormationNItems = $this->collectFiches($parcoursN, 'formation');
        /** @var array<string, FicheMatiere> $allFormationNFichesBySigle */
        $allFormationNFichesBySigle = [];
        /** @var array<int, FicheMatiere> $allFormationNFichesByOriginId */
        $allFormationNFichesByOriginId = [];
        foreach ($allFormationNItems as $item) {
            $fm = $item['fiche'];
            $sigle = strtoupper(trim((string) $fm->getSigle()));
            $type = (string) $fm->getTypeMatiere();
            if ($sigle !== '') {
                $allFormationNFichesBySigle[$type . '_' . $sigle] = $fm;
            }
            $orig = $fm->getFicheMatiereOrigineCopie();
            if ($orig !== null && $orig->getId() !== null) {
                $allFormationNFichesByOriginId[$orig->getId()] = $fm;
            }
        }

        // Indexation des fiches N1
        /** @var array<int, array{fiche: FicheMatiere, source: string}> $fichesN1ById */
        $fichesN1ById = [];
        /** @var array<string, array{fiche: FicheMatiere, source: string}> $fichesN1ByKey */
        $fichesN1ByKey = [];
        foreach ($itemsN1 as $itemN1) {
            $fmN1 = $itemN1['fiche'];
            if ($fmN1->getId() !== null) {
                $fichesN1ById[$fmN1->getId()] = $itemN1;
            }
            $sigle = strtoupper(trim((string) $fmN1->getSigle()));
            $type = (string) $fmN1->getTypeMatiere();
            if ($sigle !== '') {
                $fichesN1ByKey[$type . '_' . $sigle] = $itemN1;
            }
        }

        /** @var array<string, bool> $matchedN1Keys */
        $matchedN1Keys = [];

        // Comparer toutes les fiches de N
        foreach ($itemsN as $itemN) {
            $fmN = $itemN['fiche'];
            $sourceN = $itemN['source'];
            $fmN1 = $fmN->getFicheMatiereOrigineCopie();
            $linkBroken = false;
            $isDirectOrigin = false;

            if ($fmN1 !== null) {
                if ($fmN1->getId() !== null && isset($fichesN1ById[$fmN1->getId()])) {
                    $isDirectOrigin = true;
                } else {
                    foreach ($itemsN1 as $it) {
                        if ($it['fiche'] === $fmN1) {
                            $isDirectOrigin = true;
                            break;
                        }
                    }
                }
            }

            if (!$isDirectOrigin) {
                // Tenter de faire correspondre par type + sigle
                $sigle = strtoupper(trim((string) $fmN->getSigle()));
                $type = (string) $fmN->getTypeMatiere();
                $sigleKey = $type . '_' . $sigle;

                if (isset($fichesN1ByKey[$sigleKey])) {
                    $itemN1 = $fichesN1ByKey[$sigleKey];
                    $fmN1 = $itemN1['fiche'];
                    $linkBroken = true;
                    $anomalies[] = new AnomalyItem(
                        severity: AnomalyItem::SEVERITY_WARNING,
                        category: 'fiche_matiere',
                        message: sprintf(
                            'Fiche matière "%s" (%s) : lien d\'origine manquant vers N-1 (fiche N-1 #%d correspondante retrouvée par sigle).',
                            (string) $fmN->getLibelle(),
                            (string) $fmN->getSigle(),
                            (int) $fmN1->getId()
                        ),
                        context: ['ficheNId' => $fmN->getId(), 'ficheN1Id' => $fmN1->getId()],
                    );
                } else {
                    $fmN1 = null;
                }
            }

            if ($fmN1 !== null) {
                $matchedN1Keys[$this->getFicheUniqueKey($fmN1)] = true;

                $diffs = $this->diffFicheFields($fmN1, $fmN);
                $hasDiff = count(array_filter($diffs, static fn (FieldDiff $d) => $d->isDiff)) > 0;
                $status = $hasDiff ? FicheMatiereDiff::STATUS_MODIFIED : FicheMatiereDiff::STATUS_IDENTICAL;

                if ($linkBroken) {
                    $status = FicheMatiereDiff::STATUS_UNLINKED;
                }

                $result[] = new FicheMatiereDiff(
                    status: $status,
                    sigle: (string) ($fmN->getSigle() ?? $fmN1->getSigle() ?? ''),
                    typeMatiere: (string) ($fmN->getTypeMatiere() ?? $fmN1->getTypeMatiere() ?? ''),
                    libelleN1: $fmN1->getLibelle(),
                    libelleN: $fmN->getLibelle(),
                    ficheN1: $fmN1,
                    ficheN: $fmN,
                    differences: $diffs,
                    isOriginLinkBroken: $linkBroken,
                    scopeSource: $sourceN,
                );
            } else {
                // Fiche ajoutée en N
                $result[] = new FicheMatiereDiff(
                    status: FicheMatiereDiff::STATUS_ADDED_IN_N,
                    sigle: (string) $fmN->getSigle(),
                    typeMatiere: (string) $fmN->getTypeMatiere(),
                    libelleN1: null,
                    libelleN: $fmN->getLibelle(),
                    ficheN1: null,
                    ficheN: $fmN,
                    scopeSource: $sourceN,
                );
            }
        }

        // Fiches présentes en N-1 mais non trouvées dans $itemsN
        foreach ($itemsN1 as $keyN1 => $itemN1) {
            if (isset($matchedN1Keys[$keyN1])) {
                continue;
            }

            $fmN1 = $itemN1['fiche'];
            $sourceN1 = $itemN1['source'];
            $sigle = strtoupper(trim((string) $fmN1->getSigle()));
            $type = (string) $fmN1->getTypeMatiere();
            $sigleKey = $type . '_' . $sigle;

            // Vérifier si cette fiche existe dans la formation en N
            $existingInFormationN = null;
            if ($fmN1->getId() !== null && isset($allFormationNFichesByOriginId[$fmN1->getId()])) {
                $existingInFormationN = $allFormationNFichesByOriginId[$fmN1->getId()];
            } elseif (isset($allFormationNFichesBySigle[$sigleKey])) {
                $existingInFormationN = $allFormationNFichesBySigle[$sigleKey];
            }

            if ($existingInFormationN !== null) {
                // La fiche existe en N dans la formation, mais elle est détachée de ce parcours !
                $diffs = $this->diffFicheFields($fmN1, $existingInFormationN);
                $hasDiff = count(array_filter($diffs, static fn (FieldDiff $d) => $d->isDiff)) > 0;
                $status = $hasDiff ? FicheMatiereDiff::STATUS_MODIFIED : FicheMatiereDiff::STATUS_IDENTICAL;

                $anomalies[] = new AnomalyItem(
                    severity: AnomalyItem::SEVERITY_WARNING,
                    category: 'fiche_matiere',
                    message: sprintf(
                        'Fiche matière "%s" (%s) : présente dans la formation en N (#%d) mais détachée de ce parcours (non mutualisée et aucun EC lié).',
                        (string) $fmN1->getLibelle(),
                        (string) $fmN1->getSigle(),
                        (int) $existingInFormationN->getId()
                    ),
                    context: ['ficheN1Id' => $fmN1->getId(), 'ficheNId' => $existingInFormationN->getId()],
                );

                $result[] = new FicheMatiereDiff(
                    status: $status,
                    sigle: (string) ($fmN1->getSigle() ?? ''),
                    typeMatiere: (string) ($fmN1->getTypeMatiere() ?? ''),
                    libelleN1: $fmN1->getLibelle(),
                    libelleN: $existingInFormationN->getLibelle(),
                    ficheN1: $fmN1,
                    ficheN: $existingInFormationN,
                    differences: $diffs,
                    isOriginLinkBroken: false,
                    scopeSource: 'formation',
                    isDetachedFromParcours: true,
                );
            } else {
                // Totalement manquante en N
                $anomalies[] = new AnomalyItem(
                    severity: AnomalyItem::SEVERITY_ERROR,
                    category: 'fiche_matiere',
                    message: sprintf(
                        'Fiche matière N-1 manquante en N : "%s" (%s, ID #%d).',
                        (string) $fmN1->getLibelle(),
                        (string) $fmN1->getSigle(),
                        (int) $fmN1->getId()
                    ),
                    context: ['ficheN1Id' => $fmN1->getId()],
                );

                $result[] = new FicheMatiereDiff(
                    status: FicheMatiereDiff::STATUS_MISSING_IN_N,
                    sigle: (string) $fmN1->getSigle(),
                    typeMatiere: (string) $fmN1->getTypeMatiere(),
                    libelleN1: $fmN1->getLibelle(),
                    libelleN: null,
                    ficheN1: $fmN1,
                    ficheN: null,
                    scopeSource: $sourceN1,
                );
            }
        }

        // Trier par sigle
        usort($result, static fn (FicheMatiereDiff $a, FicheMatiereDiff $b) => strnatcasecmp($a->sigle, $b->sigle));

        return $result;
    }

    /**
     * @return array<string, FieldDiff>
     */
    private function diffFicheFields(FicheMatiere $f1, FicheMatiere $f2): array
    {
        $fields = [
            'libelle' => ['label' => 'Intitulé', 'v1' => $f1->getLibelle(), 'v2' => $f2->getLibelle()],
            'sigle' => ['label' => 'Sigle', 'v1' => $f1->getSigle(), 'v2' => $f2->getSigle()],
            'typeMatiere' => ['label' => 'Type', 'v1' => (string) $f1->getTypeMatiere(), 'v2' => (string) $f2->getTypeMatiere()],
            'ects' => ['label' => 'Crédits ECTS', 'v1' => (float) ($f1->getEcts() ?? 0.0), 'v2' => (float) ($f2->getEcts() ?? 0.0)],
            'cmPres' => ['label' => 'CM Présentiel', 'v1' => (float) ($f1->getVolumeCmPresentiel() ?? 0.0), 'v2' => (float) ($f2->getVolumeCmPresentiel() ?? 0.0)],
            'tdPres' => ['label' => 'TD Présentiel', 'v1' => (float) ($f1->getVolumeTdPresentiel() ?? 0.0), 'v2' => (float) ($f2->getVolumeTdPresentiel() ?? 0.0)],
            'tpPres' => ['label' => 'TP Présentiel', 'v1' => (float) ($f1->getVolumeTpPresentiel() ?? 0.0), 'v2' => (float) ($f2->getVolumeTpPresentiel() ?? 0.0)],
            'tePres' => ['label' => 'TE Présentiel', 'v1' => (float) ($f1->getVolumeTe() ?? 0.0), 'v2' => (float) ($f2->getVolumeTe() ?? 0.0)],
            'cmDist' => ['label' => 'CM Distanciel', 'v1' => (float) ($f1->getVolumeCmDistanciel() ?? 0.0), 'v2' => (float) ($f2->getVolumeCmDistanciel() ?? 0.0)],
            'tdDist' => ['label' => 'TD Distanciel', 'v1' => (float) ($f1->getVolumeTdDistanciel() ?? 0.0), 'v2' => (float) ($f2->getVolumeTdDistanciel() ?? 0.0)],
            'tpDist' => ['label' => 'TP Distanciel', 'v1' => (float) ($f1->getVolumeTpDistanciel() ?? 0.0), 'v2' => (float) ($f2->getVolumeTpDistanciel() ?? 0.0)],
            'totalEtudiant' => ['label' => 'Total Heures Étudiant', 'v1' => (float) $f1->getVolumeEtudiant(), 'v2' => (float) $f2->getVolumeEtudiant()],
            'codeApogee' => ['label' => 'Code Apogée', 'v1' => $f1->getCodeApogee(), 'v2' => $f2->getCodeApogee()],
            'mutualise' => ['label' => 'Mutualisé', 'v1' => (bool) $f1->isEnseignementMutualise(), 'v2' => (bool) $f2->isEnseignementMutualise()],
            'sansNote' => ['label' => 'Sans note', 'v1' => (bool) $f1->isSansNote(), 'v2' => (bool) $f2->isSansNote()],
            'sansHeures' => ['label' => 'Sans heures', 'v1' => (bool) $f1->isSansHeures(), 'v2' => (bool) $f2->isSansHeures()],
            'quitus' => ['label' => 'Quitus', 'v1' => (bool) $f1->isQuitus(), 'v2' => (bool) $f2->isQuitus()],
        ];

        $result = [];
        foreach ($fields as $key => $info) {
            $isDiff = false;
            if (is_float($info['v1']) || is_float($info['v2'])) {
                $isDiff = abs((float) $info['v1'] - (float) $info['v2']) > 0.001;
            } elseif (is_bool($info['v1']) || is_bool($info['v2'])) {
                $isDiff = (bool) $info['v1'] !== (bool) $info['v2'];
            } else {
                $isDiff = trim((string) $info['v1']) !== trim((string) $info['v2']);
            }

            $result[$key] = new FieldDiff(
                field: $key,
                label: $info['label'],
                valN1: $info['v1'],
                valN: $info['v2'],
                isDiff: $isDiff,
            );
        }

        return $result;
    }

    /**
     * @param list<AnomalyItem> $anomalies
     * @return list<SemestreDiff>
     */
    private function compareStructure(Parcours $parcoursN, Parcours $parcoursN1, array &$anomalies): array
    {
        /** @var list<SemestreDiff> $result */
        $result = [];

        /** @var array<int, SemestreParcours> $semestresN1ByOrdre */
        $semestresN1ByOrdre = [];
        foreach ($parcoursN1->getSemestreParcours() as $spN1) {
            $semestresN1ByOrdre[$spN1->getOrdre()] = $spN1;
        }

        /** @var array<int, SemestreParcours> $semestresNByOrdre */
        $semestresNByOrdre = [];
        foreach ($parcoursN->getSemestreParcours() as $spN) {
            $semestresNByOrdre[$spN->getOrdre()] = $spN;
        }

        $allOrdres = array_unique(array_merge(array_keys($semestresN1ByOrdre), array_keys($semestresNByOrdre)));
        sort($allOrdres);

        foreach ($allOrdres as $ordre) {
            $spN1 = $semestresN1ByOrdre[$ordre] ?? null;
            $spN = $semestresNByOrdre[$ordre] ?? null;

            $ouvertN1 = $spN1 !== null && $spN1->isOuvert() === true;
            $ouvertN = $spN !== null && $spN->isOuvert() === true;

            $libelle = sprintf('Semestre %d', $ordre);

            $uesDiff = $this->compareUesSemestre($spN1, $spN, $parcoursN, $parcoursN1, $anomalies, $ordre);

            $totauxN1 = $this->calcSemestreTotals($spN1, $parcoursN1);
            $totauxN = $this->calcSemestreTotals($spN, $parcoursN);
            $totauxDiff = [];
            foreach ($totauxN as $k => $val) {
                $totauxDiff[$k] = $val - ($totauxN1[$k] ?? 0.0);
            }

            if (abs($totauxDiff['ects'] ?? 0.0) > 0.001) {
                $anomalies[] = new AnomalyItem(
                    severity: AnomalyItem::SEVERITY_WARNING,
                    category: 'volume',
                    message: sprintf(
                        'Semestre %d : écart d\'ECTS (N-1 = %.1f, N = %.1f, écart : %+.1f).',
                        $ordre,
                        $totauxN1['ects'] ?? 0.0,
                        $totauxN['ects'] ?? 0.0,
                        $totauxDiff['ects']
                    )
                );
            }

            $result[] = new SemestreDiff(
                ordre: $ordre,
                libelle: $libelle,
                ouvertN1: $ouvertN1,
                ouvertN: $ouvertN,
                ues: $uesDiff,
                totauxN1: $totauxN1,
                totauxN: $totauxN,
                totauxDiff: $totauxDiff,
            );
        }

        return $result;
    }

    /**
     * @param list<AnomalyItem> $anomalies
     * @return list<UeDiff>
     */
    private function compareUesSemestre(
        ?SemestreParcours $spN1,
        ?SemestreParcours $spN,
        Parcours $parcoursN,
        Parcours $parcoursN1,
        array &$anomalies,
        int $semestreOrdre
    ): array {
        /** @var list<UeDiff> $result */
        $result = [];

        $semN1 = $spN1?->getSemestre();
        $semN = $spN?->getSemestre();

        /** @var array<int|string, Ue> $uesN1 */
        $uesN1 = [];
        if ($semN1 !== null) {
            foreach ($semN1->getUes() as $ue) {
                if ($ue->getUeParent() === null) {
                    $key = $ue->getOrdre() ?? count($uesN1);
                    $uesN1[$key] = $ue;
                }
            }
        }

        /** @var array<int|string, Ue> $uesN */
        $uesN = [];
        if ($semN !== null) {
            foreach ($semN->getUes() as $ue) {
                if ($ue->getUeParent() === null) {
                    $key = $ue->getOrdre() ?? count($uesN);
                    $uesN[$key] = $ue;
                }
            }
        }

        $allKeys = array_unique(array_merge(array_keys($uesN1), array_keys($uesN)));
        sort($allKeys);

        foreach ($allKeys as $k) {
            $ueN1 = $uesN1[$k] ?? null;
            $ueN = $uesN[$k] ?? null;

            if ($ueN1 !== null && $ueN !== null) {
                $status = UeDiff::STATUS_IDENTICAL;
                if ($ueN1->getLibelle() !== $ueN->getLibelle()) {
                    $status = UeDiff::STATUS_MODIFIED;
                }

                $ecsDiff = $this->compareEcsUe($ueN1, $ueN, $parcoursN, $parcoursN1, $anomalies, $semestreOrdre);

                $result[] = new UeDiff(
                    status: $status,
                    libelle: (string) ($ueN->getLibelle() ?? $ueN1->getLibelle()),
                    ordre: $ueN->getOrdre() ?? $ueN1->getOrdre(),
                    codeApogee: $ueN->getCodeApogee() ?? $ueN1->getCodeApogee(),
                    ueN1: $ueN1,
                    ueN: $ueN,
                    ecs: $ecsDiff,
                );
            } elseif ($ueN !== null) {
                $ecsDiff = $this->compareEcsUe(null, $ueN, $parcoursN, $parcoursN1, $anomalies, $semestreOrdre);
                $result[] = new UeDiff(
                    status: UeDiff::STATUS_ADDED_IN_N,
                    libelle: (string) $ueN->getLibelle(),
                    ordre: $ueN->getOrdre(),
                    codeApogee: $ueN->getCodeApogee(),
                    ueN1: null,
                    ueN: $ueN,
                    ecs: $ecsDiff,
                );
            } elseif ($ueN1 !== null) {
                $anomalies[] = new AnomalyItem(
                    severity: AnomalyItem::SEVERITY_ERROR,
                    category: 'structure',
                    message: sprintf('Semestre %d : UE N-1 manquante en N ("%s", ID #%d).', $semestreOrdre, (string) $ueN1->getLibelle(), (int) $ueN1->getId())
                );
                $ecsDiff = $this->compareEcsUe($ueN1, null, $parcoursN, $parcoursN1, $anomalies, $semestreOrdre);
                $result[] = new UeDiff(
                    status: UeDiff::STATUS_MISSING_IN_N,
                    libelle: (string) $ueN1->getLibelle(),
                    ordre: $ueN1->getOrdre(),
                    codeApogee: $ueN1->getCodeApogee(),
                    ueN1: $ueN1,
                    ueN: null,
                    ecs: $ecsDiff,
                );
            }
        }

        return $result;
    }

    /**
     * @param list<AnomalyItem> $anomalies
     * @return list<EcDiff>
     */
    private function compareEcsUe(
        ?Ue $ueN1,
        ?Ue $ueN,
        Parcours $parcoursN,
        Parcours $parcoursN1,
        array &$anomalies,
        int $semestreOrdre
    ): array {
        /** @var list<EcDiff> $result */
        $result = [];

        /** @var array<int, ElementConstitutif> $ecsN1 */
        $ecsN1 = [];
        if ($ueN1 !== null) {
            foreach ($ueN1->getElementConstitutifs() as $ec) {
                if ($ec->getParcours() === null || $ec->getParcours() === $parcoursN1) {
                    $ecsN1[] = $ec;
                }
            }
        }

        /** @var array<int, ElementConstitutif> $ecsN */
        $ecsN = [];
        if ($ueN !== null) {
            foreach ($ueN->getElementConstitutifs() as $ec) {
                if ($ec->getParcours() === null || $ec->getParcours() === $parcoursN) {
                    $ecsN[] = $ec;
                }
            }
        }

        /** @var array<int, bool> $matchedN1 */
        $matchedN1 = [];

        foreach ($ecsN as $ecN) {
            $ecN1 = $ecN->getEcOrigineCopie();
            $linkBroken = false;

            if ($ecN1 === null) {
                // Tenter de retrouver par code ou libellé
                foreach ($ecsN1 as $cand) {
                    if ($ecN->getCode() !== null && strcasecmp((string) $cand->getCode(), (string) $ecN->getCode()) === 0) {
                        $ecN1 = $cand;
                        $linkBroken = true;
                        break;
                    }
                    if ($ecN->getLibelle() !== null && strcasecmp((string) $cand->getLibelle(), (string) $ecN->getLibelle()) === 0) {
                        $ecN1 = $cand;
                        $linkBroken = true;
                        break;
                    }
                }
            }

            if ($ecN1 !== null) {
                $matchedN1[spl_object_id($ecN1)] = true;

                // Vérification du rattachement de la fiche matière
                $fmN1 = $ecN1->getFicheMatiere();
                $fmN = $ecN->getFicheMatiere();
                $attachStatus = EcDiff::ATTACH_OK;
                $attachMsg = null;

                if ($fmN1 !== null && $fmN === null) {
                    $attachStatus = EcDiff::ATTACH_MISSING_FICHE;
                    $attachMsg = sprintf('Fiche matière détachée ! En N-1 l\'EC était rattaché à "%s" (%s).', (string) $fmN1->getLibelle(), (string) $fmN1->getSigle());
                    $anomalies[] = new AnomalyItem(
                        severity: AnomalyItem::SEVERITY_ERROR,
                        category: 'ec_attachement',
                        message: sprintf(
                            'S%d : L\'EC "%s" (code %s) a perdu sa fiche matière en N ! (Fiche N-1 : "%s" [%s]).',
                            $semestreOrdre,
                            (string) $ecN->getLibelle(),
                            (string) $ecN->getCode(),
                            (string) $fmN1->getLibelle(),
                            (string) $fmN1->getSigle()
                        ),
                        context: ['ecNId' => $ecN->getId(), 'ecN1Id' => $ecN1->getId(), 'ficheN1Id' => $fmN1->getId()],
                    );
                } elseif ($fmN1 !== null && $fmN !== null) {
                    $matchesOrigin = $fmN->getFicheMatiereOrigineCopie() === $fmN1;
                    $matchesSigle = $fmN->getSigle() !== null && strcasecmp((string) $fmN->getSigle(), (string) $fmN1->getSigle()) === 0;

                    if (!$matchesOrigin && !$matchesSigle) {
                        $attachStatus = EcDiff::ATTACH_MISMATCHED_FICHE;
                        $attachMsg = sprintf('Fiche matière incohérente (N-1 : %s, N : %s).', (string) $fmN1->getSigle(), (string) $fmN->getSigle());
                        $anomalies[] = new AnomalyItem(
                            severity: AnomalyItem::SEVERITY_ERROR,
                            category: 'ec_attachement',
                            message: sprintf(
                                'S%d : L\'EC "%s" est rattaché à la fiche "%s" en N alors qu\'il était rattaché à "%s" en N-1.',
                                $semestreOrdre,
                                (string) $ecN->getLibelle(),
                                (string) ($fmN->getSigle() ?? $fmN->getLibelle()),
                                (string) ($fmN1->getSigle() ?? $fmN1->getLibelle())
                            ),
                            context: ['ecNId' => $ecN->getId(), 'ecN1Id' => $ecN1->getId()],
                        );
                    }
                } elseif ($fmN !== null) {
                    $attachStatus = EcDiff::ATTACH_NEW_FICHE;
                    $attachMsg = sprintf('Nouvelle fiche rattachée en N (%s).', (string) $fmN->getSigle());
                }

                $diffs = $this->diffEcFields($ecN1, $ecN);
                $hasDiff = count(array_filter($diffs, static fn (FieldDiff $d) => $d->isDiff)) > 0;
                $status = ($hasDiff || $attachStatus !== EcDiff::ATTACH_OK) ? EcDiff::STATUS_MODIFIED : EcDiff::STATUS_IDENTICAL;

                $result[] = new EcDiff(
                    status: $status,
                    code: $ecN->getCode() ?? $ecN1->getCode(),
                    libelle: (string) ($ecN->getLibelle() ?? $ecN1->getLibelle()),
                    ordre: $ecN->getOrdre() ?? $ecN1->getOrdre(),
                    ecN1: $ecN1,
                    ecN: $ecN,
                    attachmentStatus: $attachStatus,
                    attachmentMessage: $attachMsg,
                    ficheN1: $fmN1,
                    ficheN: $fmN,
                    differences: $diffs,
                    isOriginLinkBroken: $linkBroken,
                );
            } else {
                // EC ajouté en N
                $result[] = new EcDiff(
                    status: EcDiff::STATUS_ADDED_IN_N,
                    code: $ecN->getCode(),
                    libelle: (string) $ecN->getLibelle(),
                    ordre: $ecN->getOrdre(),
                    ecN1: null,
                    ecN: $ecN,
                    attachmentStatus: $ecN->getFicheMatiere() !== null ? EcDiff::ATTACH_NEW_FICHE : EcDiff::ATTACH_NONE,
                    attachmentMessage: null,
                    ficheN1: null,
                    ficheN: $ecN->getFicheMatiere(),
                );
            }
        }

        // ECs présents en N-1 mais absents en N
        foreach ($ecsN1 as $ecN1) {
            if (!isset($matchedN1[spl_object_id($ecN1)])) {
                $anomalies[] = new AnomalyItem(
                    severity: AnomalyItem::SEVERITY_ERROR,
                    category: 'structure',
                    message: sprintf(
                        'S%d : EC N-1 manquant en N : "%s" (code %s, ID #%d).',
                        $semestreOrdre,
                        (string) $ecN1->getLibelle(),
                        (string) $ecN1->getCode(),
                        (int) $ecN1->getId()
                    ),
                    context: ['ecN1Id' => $ecN1->getId()],
                );

                $result[] = new EcDiff(
                    status: EcDiff::STATUS_MISSING_IN_N,
                    code: $ecN1->getCode(),
                    libelle: (string) $ecN1->getLibelle(),
                    ordre: $ecN1->getOrdre(),
                    ecN1: $ecN1,
                    ecN: null,
                    attachmentStatus: EcDiff::ATTACH_NONE,
                    attachmentMessage: null,
                    ficheN1: $ecN1->getFicheMatiere(),
                    ficheN: null,
                );
            }
        }

        return $result;
    }

    /**
     * @return array<string, FieldDiff>
     */
    private function diffEcFields(ElementConstitutif $e1, ElementConstitutif $e2): array
    {
        $fields = [
            'libelle' => ['label' => 'Libellé', 'v1' => $e1->getLibelle(), 'v2' => $e2->getLibelle()],
            'code' => ['label' => 'Code', 'v1' => $e1->getCode(), 'v2' => $e2->getCode()],
            'ects' => ['label' => 'Crédits ECTS', 'v1' => (float) ($e1->getEcts() ?? 0.0), 'v2' => (float) ($e2->getEcts() ?? 0.0)],
            'ordre' => ['label' => 'Ordre', 'v1' => (int) ($e1->getOrdre() ?? 0), 'v2' => (int) ($e2->getOrdre() ?? 0)],
            'typeEc' => ['label' => 'Type EC', 'v1' => $e1->getTypeEc()?->getLibelle(), 'v2' => $e2->getTypeEc()?->getLibelle()],
            'natureUeEc' => ['label' => 'Nature', 'v1' => $e1->getNatureUeEc()?->getLibelle(), 'v2' => $e2->getNatureUeEc()?->getLibelle()],
            'modalite' => ['label' => 'Modalité', 'v1' => $e1->getModaliteEnseignementValue(), 'v2' => $e2->getModaliteEnseignementValue()],
        ];

        $result = [];
        foreach ($fields as $key => $info) {
            $isDiff = false;
            if (is_float($info['v1']) || is_float($info['v2'])) {
                $isDiff = abs((float) $info['v1'] - (float) $info['v2']) > 0.001;
            } elseif (is_int($info['v1']) || is_int($info['v2'])) {
                $isDiff = (int) $info['v1'] !== (int) $info['v2'];
            } else {
                $isDiff = trim((string) $info['v1']) !== trim((string) $info['v2']);
            }

            $result[$key] = new FieldDiff(
                field: $key,
                label: $info['label'],
                valN1: $info['v1'],
                valN: $info['v2'],
                isDiff: $isDiff,
            );
        }

        return $result;
    }

    /**
     * @param list<AnomalyItem> $anomalies
     * @return list<MutualisationDiff>
     */
    private function compareMutualisations(Parcours $parcoursN, Parcours $parcoursN1, array &$anomalies): array
    {
        /** @var list<MutualisationDiff> $result */
        $result = [];

        /** @var array<string, FicheMatiere> $mutusN1 */
        $mutusN1 = [];
        foreach ($parcoursN1->getFicheMatiereParcours() as $fmpN1) {
            $fm = $fmpN1->getFicheMatiere();
            if ($fm !== null) {
                $sigle = strtoupper(trim((string) $fm->getSigle()));
                $mutusN1[$sigle] = $fm;
            }
        }

        /** @var array<string, FicheMatiere> $mutusN */
        $mutusN = [];
        foreach ($parcoursN->getFicheMatiereParcours() as $fmpN) {
            $fm = $fmpN->getFicheMatiere();
            if ($fm !== null) {
                $sigle = strtoupper(trim((string) $fm->getSigle()));
                $mutusN[$sigle] = $fm;

                // Vérifier si c'est une mutualisation avec une formation externe suspecte
                $srcParcours = $fm->getParcours();
                $isInternal = $srcParcours === $parcoursN || ($srcParcours?->getFormation() !== null && $srcParcours->getFormation() === $parcoursN->getFormation());
                if (!$isInternal) {
                    $anomalies[] = new AnomalyItem(
                        severity: AnomalyItem::SEVERITY_WARNING,
                        category: 'mutualisation',
                        message: sprintf(
                            'Mutualisation externe détectée sur la fiche "%s" (parcours source : %s [Formation #%d]).',
                            (string) $fm->getSigle(),
                            (string) ($srcParcours?->getLibelle() ?? 'Inconnu'),
                            (int) ($srcParcours?->getFormation()?->getId() ?? 0)
                        )
                    );
                }
            }
        }

        // Mutualisations conservées ou perdues
        foreach ($mutusN1 as $sigle => $fmN1) {
            if (isset($mutusN[$sigle])) {
                $fmN = $mutusN[$sigle];
                $result[] = new MutualisationDiff(
                    status: MutualisationDiff::STATUS_CONSERVED,
                    sigle: $sigle,
                    libelle: (string) $fmN->getLibelle(),
                    sourceParcours: (string) ($fmN->getParcours()?->getLibelle() ?? 'Tronc commun'),
                    targetParcours: (string) $parcoursN->getLibelle(),
                    ficheMatiere: $fmN,
                );
            } else {
                $anomalies[] = new AnomalyItem(
                    severity: AnomalyItem::SEVERITY_ERROR,
                    category: 'mutualisation',
                    message: sprintf(
                        'Mutualisation perdue en N : la fiche "%s" (%s) était mutualisée sur ce parcours en N-1 mais ne l\'est plus.',
                        (string) $fmN1->getLibelle(),
                        $sigle
                    )
                );

                $result[] = new MutualisationDiff(
                    status: MutualisationDiff::STATUS_LOST_IN_N,
                    sigle: $sigle,
                    libelle: (string) $fmN1->getLibelle(),
                    sourceParcours: (string) ($fmN1->getParcours()?->getLibelle() ?? 'Tronc commun'),
                    targetParcours: (string) $parcoursN->getLibelle(),
                    ficheMatiere: $fmN1,
                );
            }
        }

        // Nouvelles mutualisations
        foreach ($mutusN as $sigle => $fmN) {
            if (!isset($mutusN1[$sigle])) {
                $result[] = new MutualisationDiff(
                    status: MutualisationDiff::STATUS_ADDED_IN_N,
                    sigle: $sigle,
                    libelle: (string) $fmN->getLibelle(),
                    sourceParcours: (string) ($fmN->getParcours()?->getLibelle() ?? 'Tronc commun'),
                    targetParcours: (string) $parcoursN->getLibelle(),
                    ficheMatiere: $fmN,
                );
            }
        }

        return $result;
    }

    /**
     * @return array<string, float>
     */
    private function calcSemestreTotals(?SemestreParcours $sp, Parcours $parcours): array
    {
        $totals = [
            'ects' => 0.0,
            'cmPres' => 0.0,
            'tdPres' => 0.0,
            'tpPres' => 0.0,
            'tePres' => 0.0,
            'totalPres' => 0.0,
            'totalDist' => 0.0,
            'totalHeures' => 0.0,
        ];

        if ($sp === null || !$sp->isOuvert()) {
            return $totals;
        }

        $sem = $sp->getSemestre();
        if ($sem === null || $sem->isNonDispense()) {
            return $totals;
        }

        /** @var array<int, bool> $seenFiches */
        $seenFiches = [];

        foreach ($sem->getUes() as $ue) {
            foreach ($ue->getElementConstitutifs() as $ec) {
                if ($ec->getParcours() !== null && $ec->getParcours() !== $parcours) {
                    continue;
                }

                $totals['ects'] += (float) ($ec->getEcts() ?? 0.0);

                $fm = $ec->getFicheMatiere();
                if ($fm !== null && $fm->getId() !== null && !isset($seenFiches[$fm->getId()])) {
                    $seenFiches[$fm->getId()] = true;

                    $cm = (float) ($fm->getVolumeCmPresentiel() ?? 0.0);
                    $td = (float) ($fm->getVolumeTdPresentiel() ?? 0.0);
                    $tp = (float) ($fm->getVolumeTpPresentiel() ?? 0.0);
                    $te = (float) ($fm->getVolumeTe() ?? 0.0);
                    $cmd = (float) ($fm->getVolumeCmDistanciel() ?? 0.0);
                    $tdd = (float) ($fm->getVolumeTdDistanciel() ?? 0.0);
                    $tpd = (float) ($fm->getVolumeTpDistanciel() ?? 0.0);

                    $totals['cmPres'] += $cm;
                    $totals['tdPres'] += $td;
                    $totals['tpPres'] += $tp;
                    $totals['tePres'] += $te;
                    $totals['totalPres'] += ($cm + $td + $tp + $te);
                    $totals['totalDist'] += ($cmd + $tdd + $tpd);
                    $totals['totalHeures'] += ($cm + $td + $tp + $te + $cmd + $tdd + $tpd);
                }
            }
        }

        return $totals;
    }

    /**
     * @param list<SemestreDiff> $semestresDiff
     */
    private function calculateTotauxGlobal(array $semestresDiff): TotauxDiff
    {
        $ectsN1 = 0.0;
        $ectsN = 0.0;
        $cmPresN1 = 0.0;
        $cmPresN = 0.0;
        $tdPresN1 = 0.0;
        $tdPresN = 0.0;
        $tpPresN1 = 0.0;
        $tpPresN = 0.0;
        $tePresN1 = 0.0;
        $tePresN = 0.0;
        $totalPresN1 = 0.0;
        $totalPresN = 0.0;
        $totalDistN1 = 0.0;
        $totalDistN = 0.0;
        $totalHeuresN1 = 0.0;
        $totalHeuresN = 0.0;

        foreach ($semestresDiff as $sem) {
            $ectsN1 += $sem->totauxN1['ects'] ?? 0.0;
            $ectsN += $sem->totauxN['ects'] ?? 0.0;
            $cmPresN1 += $sem->totauxN1['cmPres'] ?? 0.0;
            $cmPresN += $sem->totauxN['cmPres'] ?? 0.0;
            $tdPresN1 += $sem->totauxN1['tdPres'] ?? 0.0;
            $tdPresN += $sem->totauxN['tdPres'] ?? 0.0;
            $tpPresN1 += $sem->totauxN1['tpPres'] ?? 0.0;
            $tpPresN += $sem->totauxN['tpPres'] ?? 0.0;
            $tePresN1 += $sem->totauxN1['tePres'] ?? 0.0;
            $tePresN += $sem->totauxN['tePres'] ?? 0.0;
            $totalPresN1 += $sem->totauxN1['totalPres'] ?? 0.0;
            $totalPresN += $sem->totauxN['totalPres'] ?? 0.0;
            $totalDistN1 += $sem->totauxN1['totalDist'] ?? 0.0;
            $totalDistN += $sem->totauxN['totalDist'] ?? 0.0;
            $totalHeuresN1 += $sem->totauxN1['totalHeures'] ?? 0.0;
            $totalHeuresN += $sem->totauxN['totalHeures'] ?? 0.0;
        }

        return new TotauxDiff(
            ectsN1: $ectsN1,
            ectsN: $ectsN,
            diffEcts: $ectsN - $ectsN1,
            cmPresN1: $cmPresN1,
            cmPresN: $cmPresN,
            diffCmPres: $cmPresN - $cmPresN1,
            tdPresN1: $tdPresN1,
            tdPresN: $tdPresN,
            diffTdPres: $tdPresN - $tdPresN1,
            tpPresN1: $tpPresN1,
            tpPresN: $tpPresN,
            diffTpPres: $tpPresN - $tpPresN1,
            tePresN1: $tePresN1,
            tePresN: $tePresN,
            diffTePres: $tePresN - $tePresN1,
            totalPresN1: $totalPresN1,
            totalPresN: $totalPresN,
            diffTotalPres: $totalPresN - $totalPresN1,
            totalDistN1: $totalDistN1,
            totalDistN: $totalDistN,
            diffTotalDist: $totalDistN - $totalDistN1,
            totalHeuresN1: $totalHeuresN1,
            totalHeuresN: $totalHeuresN,
            diffTotalHeures: $totalHeuresN - $totalHeuresN1,
        );
    }
}
