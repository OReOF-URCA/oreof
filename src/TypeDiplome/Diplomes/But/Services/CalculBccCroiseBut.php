<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/TypeDiplome/Diplomes/But/Services/CalculBccCroiseBut.php
 * @author davidannebicque
 * @project oreofv2
 */

namespace App\TypeDiplome\Diplomes\But\Services;

use App\Entity\Parcours;
use App\Repository\ButCompetenceRepository;

class CalculBccCroiseBut
{
    public function __construct(
        private readonly ButCompetenceRepository $butCompetenceRepository,
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function calcul(Parcours $parcours, ?int $semestreFilter = null): array
    {
        $formation = $parcours->getFormation();
        $competences = $formation !== null
            ? $this->butCompetenceRepository->findBy(['formation' => $formation])
            : [];

        $niveaux = [];
        $totalAcCount = 0;
        $mobilisedAcIds = [];

        foreach ($competences as $competence) {
            $compId = $competence->getId();
            if ($compId === null) {
                continue;
            }

            foreach ($competence->getButNiveaux() as $niveau) {
                $annee = $niveau->getAnnee();
                $semestres = match ($annee) {
                    'BUT1' => [1, 2],
                    'BUT2' => [3, 4],
                    'BUT3' => [5, 6],
                    default => [],
                };

                foreach ($semestres as $semOrdre) {
                    if (!isset($niveaux[$compId][$semOrdre])) {
                        $niveaux[$compId][$semOrdre] = [];
                    }
                    foreach ($niveau->getButApprentissageCritiques() as $bac) {
                        $niveaux[$compId][$semOrdre][] = $bac;
                        $totalAcCount++;
                    }
                }
            }
        }

        $tabFichesMatieres = [];
        $apprentissagesCritiques = [];
        $tabUeEc = [];
        $uniqueFiches = [];
        $totalMobilisations = 0;
        $semestresDisponibles = [];

        foreach ($parcours->getSemestreParcours() as $semParc) {
            $semOrdre = $semParc->getOrdre();
            if ($semOrdre === null) {
                continue;
            }

            $display = $semParc->display() ?? ('Semestre ' . $semOrdre);
            $semestresDisponibles[$semOrdre] = [
                'ordre' => $semOrdre,
                'display' => $display,
            ];

            if ($semestreFilter !== null && $semOrdre !== $semestreFilter) {
                continue;
            }

            $tabFichesMatieres[$semOrdre] = [];

            foreach ($semParc->getSemestre()?->getUes() ?? [] as $ue) {
                $ueOrdre = $ue->getOrdre() ?? 0;

                foreach ($ue->getElementConstitutifs() as $ec) {
                    $fiche = $ec->getFicheMatiere();
                    if ($fiche !== null && $fiche->getId() !== null) {
                        $ficheId = $fiche->getId();
                        $uniqueFiches[$ficheId] = $fiche;
                        $tabUeEc[$semOrdre][$ueOrdre][$ficheId] = 'x';
                        $tabFichesMatieres[$semOrdre][$ficheId] = $fiche;

                        foreach ($fiche->getApprentissagesCritiques() as $ac) {
                            if ($ac->getId() !== null) {
                                $apprentissagesCritiques[$ficheId][$ac->getId()] = 'ok';
                                $mobilisedAcIds[$ac->getId()] = true;
                                $totalMobilisations++;
                            }
                        }
                    }
                }
            }

            ksort($tabFichesMatieres[$semOrdre]);
        }

        $tauxCouverture = $totalAcCount > 0
            ? (int)round((count($mobilisedAcIds) / $totalAcCount) * 100)
            : 0;

        $stats = [
            'totalCompetences' => count($competences),
            'totalAc' => $totalAcCount,
            'totalFiches' => count($uniqueFiches),
            'totalMobilisations' => $totalMobilisations,
            'nbAcCouvertes' => count($mobilisedAcIds),
            'tauxCouverture' => $tauxCouverture,
        ];

        return [
            'parcours' => $parcours,
            'competences' => $competences,
            'niveaux' => $niveaux,
            'tabFichesMatieres' => $tabFichesMatieres,
            'apprentissagesCritiques' => $apprentissagesCritiques,
            'tabUeEc' => $tabUeEc,
            'semestresDisponibles' => $semestresDisponibles,
            'stats' => $stats,
        ];
    }
}
