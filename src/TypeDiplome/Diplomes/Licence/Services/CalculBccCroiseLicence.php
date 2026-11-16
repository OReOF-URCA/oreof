<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/TypeDiplome/Diplomes/Licence/Services/CalculBccCroiseLicence.php
 * @author davidannebicque
 * @project oreofv2
 */

namespace App\TypeDiplome\Diplomes\Licence\Services;

use App\DTO\StructureEc;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Entity\BlocCompetence;
use App\Entity\Competence;
use App\Entity\ElementConstitutif;
use App\Entity\Parcours;
use App\Repository\BlocCompetenceRepository;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\StructureInterface;

class CalculBccCroiseLicence
{
    public function __construct(
        private readonly StructureInterface       $structureParcoursLicence,
        private readonly BlocCompetenceRepository $blocCompetenceRepository,
    )
    {
    }

    /**
     * Calcule l'ensemble des données structurées pour le tableau croisé BCC / EC d'un parcours Licence.
     * Si $semestreFilter est précisé, calcule uniquement les données du semestre demandé.
     *
     * @return array<string, mixed>
     */
    public function calcul(Parcours $parcours, ?int $semestreFilter = null): array
    {
        $dto = $this->structureParcoursLicence->calcul(
            $parcours,
            new OptionsCalculStructure(withEcts: false, withBcc: true, dataFromFicheMatiere: false)
        );

        $blocCompetences = $this->blocCompetenceRepository->findByParcours($parcours);
        if (count($blocCompetences) === 0) {
            $blocCompetences = $parcours->getBlocCompetences()->toArray();
            usort(
                $blocCompetences,
                static fn(BlocCompetence $a, BlocCompetence $b) => ($a->getOrdre() ?? 0) <=> ($b->getOrdre() ?? 0)
            );
        }

        // Indexation de l'ensemble des compétences du parcours
        $allCompetences = [];
        $totalCompetencesCount = 0;
        foreach ($blocCompetences as $bloc) {
            foreach ($bloc->getCompetences() as $comp) {
                $key = $comp->getId() ?? ('comp_' . ($comp->getCode() ?? (string)$totalCompetencesCount));
                $allCompetences[$key] = $comp;
                $totalCompetencesCount++;
            }
        }

        $semestresData = [];
        $semestresDisponibles = [];
        $globalCompetenceTotals = [];
        $globalEcTotals = [];
        $uniqueLeafEcIds = [];
        $totalMobilisations = 0;

        foreach ($dto->semestres as $semestre) {
            if (!$semestre instanceof StructureSemestre) {
                continue;
            }

            $display = $semestre->semestreParcours?->display() ?? $semestre->semestre->display();
            $semestresDisponibles[$semestre->ordre] = [
                'ordre' => $semestre->ordre,
                'display' => $display,
            ];

            // Si un filtre de semestre est demandé et ne correspond pas, on saute le calcul lourd de la matrice
            if ($semestreFilter !== null && $semestre->ordre !== $semestreFilter) {
                continue;
            }

            $sData = $this->buildSemestreData($semestre, $parcours, $blocCompetences, $allCompetences);
            $semestresData[$semestre->ordre] = $sData;

            // Cumul global
            foreach ($sData['leafColumns'] as $col) {
                $uniqueLeafEcIds[$col['id']] = $col;
                if (!isset($globalEcTotals[$col['id']])) {
                    $globalEcTotals[$col['id']] = 0;
                }
                $globalEcTotals[$col['id']] += $sData['ecTotals'][$col['id']] ?? 0;
            }

            foreach ($allCompetences as $compId => $comp) {
                if (!isset($globalCompetenceTotals[$compId])) {
                    $globalCompetenceTotals[$compId] = 0;
                }
                $globalCompetenceTotals[$compId] += $sData['competenceTotals'][$compId] ?? 0;
            }

            $totalMobilisations += $sData['totalMobilisationsInSemestre'];
        }

        // Statistiques globales et couverture
        $competencesNonCouvertes = [];
        $nbCouvertes = 0;
        foreach ($allCompetences as $compId => $comp) {
            if (($globalCompetenceTotals[$compId] ?? 0) > 0) {
                $nbCouvertes++;
            } else {
                $competencesNonCouvertes[] = $comp;
            }
        }

        $ecsSansCompetence = [];
        foreach ($uniqueLeafEcIds as $ecId => $col) {
            if (($globalEcTotals[$ecId] ?? 0) === 0) {
                $ecsSansCompetence[] = $col;
            }
        }

        $tauxCouverture = $totalCompetencesCount > 0
            ? (int)round(($nbCouvertes / $totalCompetencesCount) * 100)
            : 0;

        $stats = [
            'totalBcc' => count($blocCompetences),
            'totalCompetences' => $totalCompetencesCount,
            'totalEcs' => count($uniqueLeafEcIds),
            'totalMobilisations' => $totalMobilisations,
            'nbCompetencesCouvertes' => $nbCouvertes,
            'tauxCouverture' => $tauxCouverture,
            'competencesNonCouvertes' => $competencesNonCouvertes,
            'ecsSansCompetence' => $ecsSansCompetence,
            'globalCompetenceTotals' => $globalCompetenceTotals,
            'globalEcTotals' => $globalEcTotals,
        ];

        return [
            'parcours' => $parcours,
            'dto' => $dto,
            'blocCompetences' => $blocCompetences,
            'allCompetences' => $allCompetences,
            'semestresData' => $semestresData,
            'semestresDisponibles' => $semestresDisponibles,
            'stats' => $stats,
        ];
    }

    /**
     * @param BlocCompetence[] $blocCompetences
     * @param array<int, Competence> $allCompetences
     * @return array<string, mixed>
     */
    private function buildSemestreData(
        StructureSemestre $semestre,
        Parcours          $parcours,
        array             $blocCompetences,
        array             $allCompetences
    ): array
    {
        $ueHeaders = [];
        $ecHeaders = [];
        $ecChildrenHeaders = [];
        $leafColumns = [];

        // 1. Groupes d'UE
        $ueGroups = $this->extractUeGroups($semestre);

        // 2. Vérifier si des EC enfants existent dans ce semestre
        $hasChildEcs = false;
        foreach ($ueGroups as $group) {
            foreach ($group['ecs'] as $ec) {
                if ($this->isChoixEcWithChildren($ec)) {
                    $hasChildEcs = true;
                    break 2;
                }
            }
        }

        // 3. Construction des en-têtes et colonnes feuilles
        foreach ($ueGroups as $group) {
            $ueHeaders[] = [
                'label' => $group['label'],
                'colspan' => $group['width'],
                'ue' => $group['ue'],
                'isChild' => $group['isChild'],
            ];

            foreach ($group['ecs'] as $ec) {
                if (!$ec instanceof StructureEc) {
                    continue;
                }

                $ecEntity = $ec->elementConstitutif;
                $isChoix = $this->isChoixEcWithChildren($ec);
                $children = $this->getEcChildren($ec);

                if ($isChoix) {
                    $ecHeaders[] = [
                        'ec' => $ec,
                        'code' => $ecEntity->getCode() ?? '',
                        'display' => $ecEntity->display(),
                        'libelle' => $ecEntity->getLibelle() ?? '',
                        'colspan' => count($children),
                        'rowspan' => 1,
                        'isChoix' => true,
                        'hasChildren' => true,
                    ];

                    foreach ($children as $childEc) {
                        if (!$childEc instanceof StructureEc) {
                            continue;
                        }
                        $childEntity = $childEc->elementConstitutif;
                        $ecChildrenHeaders[] = [
                            'id' => $childEntity->getId(),
                            'code' => $childEntity->getCode() ?? '',
                            'display' => $childEntity->display(),
                        ];

                        $leafColumns[] = $this->createLeafColumn(
                            $childEc,
                            $childEntity,
                            $parcours,
                            true,
                            $ecEntity->getCode(),
                            $group['label']
                        );
                    }
                } else {
                    $ecHeaders[] = [
                        'ec' => $ec,
                        'code' => $ecEntity->getCode() ?? '',
                        'display' => $ecEntity->display(),
                        'libelle' => $ecEntity->getLibelle() ?? '',
                        'colspan' => 1,
                        'rowspan' => $hasChildEcs ? 2 : 1,
                        'isChoix' => false,
                        'hasChildren' => false,
                    ];

                    $leafColumns[] = $this->createLeafColumn(
                        $ec,
                        $ecEntity,
                        $parcours,
                        false,
                        null,
                        $group['label']
                    );
                }
            }
        }

        // 4. Calcul de la matrice et des totaux du semestre
        $matrix = [];
        $competenceTotals = [];
        $ecTotals = [];
        $totalMobilisationsInSemestre = 0;

        foreach ($leafColumns as $col) {
            $ecTotals[$col['id']] = 0;
        }

        foreach ($allCompetences as $compId => $comp) {
            $competenceTotals[$compId] = 0;
            $matrix[$compId] = [];

            foreach ($leafColumns as $col) {
                $isMobilised = ($comp->getId() !== null && isset($col['competenceIds'][$comp->getId()]))
                    || ($comp->getCode() !== null && isset($col['competenceCodes'][$comp->getCode()]));

                $matrix[$compId][$col['id']] = $isMobilised;

                if ($isMobilised) {
                    $competenceTotals[$compId]++;
                    $ecTotals[$col['id']]++;
                    $totalMobilisationsInSemestre++;
                }
            }
        }

        return [
            'semestre' => $semestre,
            'ordre' => $semestre->ordre,
            'display' => $semestre->semestreParcours?->display() ?? ('Semestre ' . $semestre->ordre),
            'ueHeaders' => $ueHeaders,
            'ecHeaders' => $ecHeaders,
            'hasChildEcs' => $hasChildEcs,
            'ecChildrenHeaders' => $ecChildrenHeaders,
            'leafColumns' => $leafColumns,
            'matrix' => $matrix,
            'competenceTotals' => $competenceTotals,
            'ecTotals' => $ecTotals,
            'totalMobilisationsInSemestre' => $totalMobilisationsInSemestre,
            'totalLeafColumns' => count($leafColumns),
        ];
    }

    /**
     * @return array<int, array{label: string, colspan: int, width: int, ue: mixed, ecs: StructureEc[], isChild: bool}>
     */
    private function extractUeGroups(StructureSemestre $semestre): array
    {
        $groups = [];

        foreach ($semestre->ues as $ue) {
            if (!$ue instanceof StructureUe || $ue->ue->getUeParent() !== null) {
                continue;
            }

            $uesEnfants = $ue->uesEnfants();
            if (count($uesEnfants) === 0) {
                $ecs = $ue->elementConstitutifs;
                $width = $this->computeEcListWidth($ecs);
                if ($width > 0) {
                    $groups[] = [
                        'label' => $ue->display,
                        'colspan' => $width,
                        'width' => $width,
                        'ue' => $ue->ue,
                        'ecs' => $ecs,
                        'isChild' => false,
                    ];
                }
                continue;
            }

            foreach ($uesEnfants as $ueEnfant) {
                if (!$ueEnfant instanceof StructureUe) {
                    continue;
                }
                $ecs = $ueEnfant->elementConstitutifs;
                $width = $this->computeEcListWidth($ecs);
                if ($width > 0) {
                    $groups[] = [
                        'label' => $ueEnfant->display,
                        'colspan' => $width,
                        'width' => $width,
                        'ue' => $ueEnfant->ue,
                        'ecs' => $ecs,
                        'isChild' => true,
                    ];
                }
            }
        }

        return $groups;
    }

    /**
     * @param iterable<StructureEc> $ecs
     */
    private function computeEcListWidth(iterable $ecs): int
    {
        $width = 0;
        foreach ($ecs as $ec) {
            if ($ec instanceof StructureEc) {
                $children = $this->getEcChildren($ec);
                if ($this->isChoixEcWithChildren($ec)) {
                    $width += count($children);
                } else {
                    $width += 1;
                }
            }
        }
        return $width;
    }

    /**
     * @return StructureEc[]
     */
    private function getEcChildren(StructureEc $ec): array
    {
        return $ec->elementsConstitutifsEnfants;
    }

    private function isChoixEcWithChildren(StructureEc $ec): bool
    {
        return $ec->elementConstitutif->getNatureUeEc()?->isChoix() === true
            && count($this->getEcChildren($ec)) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function createLeafColumn(
        StructureEc        $ecStructure,
        ElementConstitutif $ecEntity,
        Parcours           $parcours,
        bool               $isChild,
        ?string            $parentCode,
        string             $ueDisplay
    ): array
    {
        $compIds = [];
        $compCodes = [];

        // 1. Depuis les BCCs calculés par StructureEc
        if (is_array($ecStructure->bccs)) {
            foreach ($ecStructure->bccs as $key => $bcc) {
                if ($bcc instanceof Competence) {
                    if ($bcc->getId() !== null) {
                        $compIds[$bcc->getId()] = true;
                    }
                    if ($bcc->getCode() !== null) {
                        $compCodes[$bcc->getCode()] = true;
                    }
                } elseif (is_string($key)) {
                    $compCodes[$key] = true;
                }
            }
        }

        // 2. Directement depuis ElementConstitutif
        foreach ($ecEntity->getCompetences() as $comp) {
            if ($comp->getId() !== null) {
                $compIds[$comp->getId()] = true;
            }
            if ($comp->getCode() !== null) {
                $compCodes[$comp->getCode()] = true;
            }
        }

        // 3. Depuis FicheMatiere
        $fiche = $ecEntity->getFicheMatiere();
        if ($fiche !== null) {
            foreach ($fiche->getCompetences() as $comp) {
                if ($comp->getId() !== null) {
                    $compIds[$comp->getId()] = true;
                }
                if ($comp->getCode() !== null) {
                    $compCodes[$comp->getCode()] = true;
                }
            }
        }

        return [
            'id' => $ecEntity->getId() ?? ('ec_' . ($ecEntity->getCode() ?? spl_object_id($ecEntity))),
            'code' => $ecEntity->getCode() ?? '',
            'display' => $ecEntity->display(),
            'libelle' => $ecEntity->getLibelle() ?? '',
            'sigle' => $ecEntity->getFicheMatiere()?->getSigle(),
            'isChoixChild' => $isChild,
            'parentCode' => $parentCode,
            'ueDisplay' => $ueDisplay,
            'nature' => $ecEntity->getNatureUeEc()?->getLibelle(),
            'competenceIds' => $compIds,
            'competenceCodes' => $compCodes,
        ];
    }
}
