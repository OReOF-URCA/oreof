<?php

namespace App\Controller\Config;

use App\Entity\Parcours;
use App\Repository\CampagneCollecteRepository;
use App\Repository\ParcoursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ParcoursEvolutionController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/parcours/evolution-campagnes', name: 'app_campagne_collecte_parcours_evolution', methods: ['GET'])]
    public function index(
        ParcoursRepository         $parcoursRepository,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response
    {
        $parcoursList = $parcoursRepository->findAllForEvolutionMatrix();
        $originByParcoursId = [];
        $rawRows = [];
        $campagnes = [];

        foreach ($campagneCollecteRepository->findAll() as $campagneCollecte) {
            $campagneId = $campagneCollecte->getId();
            if ($campagneId === null) {
                continue;
            }

            $campagnes[$campagneId] = [
                'id' => $campagneId,
                'annee' => $campagneCollecte->getAnnee() ?? 0,
                'libelle' => $campagneCollecte->getLibelle() ?? 'Campagne',
                'anneeUniversitaireLibelle' => $campagneCollecte->getAnneeUniversitaire()?->getLibelle(),
            ];
        }

        /** @var Parcours $parcours */
        foreach ($parcoursList as $parcours) {
            $parcoursId = $parcours->getId();
            $formation = $parcours->getFormation();
            if ($parcoursId === null || $formation === null) {
                continue;
            }

            $originByParcoursId[$parcoursId] = $parcours->getParcoursOrigineCopie()?->getId();
            $formationLibelle = $formation->getDisplay() ?? '—';
            $isAlternance = $parcours->isAlternance();
            $rowsByCampagne = [];

            foreach ($parcours->getDpeParcours() as $dpeParcours) {
                $campagne = $dpeParcours->getCampagneCollecte();
                $campagneId = $campagne?->getId();
                if ($campagneId === null || isset($rowsByCampagne[$campagneId])) {
                    continue;
                }

                $isOpen = !$dpeParcours->isNonOuvert();
                $rowsByCampagne[$campagneId] = [
                    'campagneId' => $campagneId,
                    'campagneAnnee' => $campagne?->getAnnee() ?? ($campagnes[$campagneId]['annee'] ?? 0),
                    'campagneLibelle' => $campagne?->getLibelle() ?? ($campagnes[$campagneId]['libelle'] ?? 'Campagne'),
                    'parcoursId' => $parcoursId,
                    'parcoursLibelle' => $parcours->getLibelle(),
                    'formationLibelle' => $formationLibelle,
                    'isOpen' => $isOpen,
                    'openLabel' => $isOpen ? 'Ouvert' : 'Fermé',
                    'isAlternance' => $isAlternance,
                    'alternanceLabel' => $isAlternance ? 'Oui' : 'Non',
                    'etatReconduction' => $dpeParcours->getEtatReconduction()?->getLibelle() ?? 'Non défini',
                ];
            }

            if (count($rowsByCampagne) === 0) {
                $campagneFallback = $formation->getDpe();
                $campagneId = $campagneFallback?->getId();
                if ($campagneId !== null) {
                    $rowsByCampagne[$campagneId] = [
                        'campagneId' => $campagneId,
                        'campagneAnnee' => $campagneFallback?->getAnnee() ?? ($campagnes[$campagneId]['annee'] ?? 0),
                        'campagneLibelle' => $campagneFallback?->getLibelle() ?? ($campagnes[$campagneId]['libelle'] ?? 'Campagne'),
                        'parcoursId' => $parcoursId,
                        'parcoursLibelle' => $parcours->getLibelle(),
                        'formationLibelle' => $formationLibelle,
                        'isOpen' => false,
                        'openLabel' => 'Jamais ouvert',
                        'isAlternance' => $isAlternance,
                        'alternanceLabel' => $isAlternance ? 'Oui' : 'Non',
                        'etatReconduction' => 'Aucun DPE',
                    ];
                }
            }

            foreach ($rowsByCampagne as $row) {
                $rawRows[] = $row;
                if (!isset($campagnes[$row['campagneId']])) {
                    $campagnes[$row['campagneId']] = [
                        'id' => $row['campagneId'],
                        'annee' => $row['campagneAnnee'] ?? 0,
                        'libelle' => $row['campagneLibelle'] ?? 'Campagne',
                        'anneeUniversitaireLibelle' => null,
                    ];
                }
            }
        }

        $rootByParcoursId = [];
        $findRootParcoursId = static function (int $parcoursId) use (&$findRootParcoursId, &$rootByParcoursId, $originByParcoursId): int {
            if (isset($rootByParcoursId[$parcoursId])) {
                return $rootByParcoursId[$parcoursId];
            }

            $originParcoursId = $originByParcoursId[$parcoursId] ?? null;
            if ($originParcoursId === null || $originParcoursId === $parcoursId || !array_key_exists($originParcoursId, $originByParcoursId)) {
                $rootByParcoursId[$parcoursId] = $parcoursId;
                return $parcoursId;
            }

            $rootByParcoursId[$parcoursId] = $findRootParcoursId($originParcoursId);
            return $rootByParcoursId[$parcoursId];
        };

        $rowsByRootParcours = [];
        foreach ($rawRows as $row) {
            $row['rootParcoursId'] = $findRootParcoursId($row['parcoursId']);
            $rowsByRootParcours[$row['rootParcoursId']][] = $row;
        }

        ksort($rowsByRootParcours);
        uasort($campagnes, static function (array $campagneA, array $campagneB): int {
            return [$campagneA['annee'], $campagneA['id']] <=> [$campagneB['annee'], $campagneB['id']];
        });
        $campagneSortIndex = [];
        foreach (array_values($campagnes) as $index => $campagne) {
            $campagneSortIndex[$campagne['id']] = $index;
        }

        $matrixRows = [];
        foreach ($rowsByRootParcours as $rootParcoursId => $rows) {
            usort($rows, static function (array $rowA, array $rowB) use ($campagneSortIndex): int {
                return ($campagneSortIndex[$rowA['campagneId']] ?? PHP_INT_MAX) <=> ($campagneSortIndex[$rowB['campagneId']] ?? PHP_INT_MAX);
            });

            $previousRow = null;
            foreach ($rows as &$row) {
                $row['changes'] = [];
                if ($previousRow !== null) {
                    $fieldLabels = [
                        'parcoursLibelle' => 'Libellé parcours',
                        'formationLibelle' => 'Formation',
                        'openLabel' => 'Ouvert/Fermé',
                        'alternanceLabel' => 'Alternance',
                    ];

                    foreach ($fieldLabels as $field => $label) {
                        if ($previousRow[$field] !== $row[$field]) {
                            $row['changes'][] = sprintf(
                                '%s : %s → %s',
                                $label,
                                (string)$previousRow[$field],
                                (string)$row[$field]
                            );
                        }
                    }
                }

                $previousRow = $row;
            }
            unset($row);

            $matrixRow = [
                'rootParcoursId' => $rootParcoursId,
                'referenceParcoursLibelle' => $rows[0]['parcoursLibelle'] ?? '—',
                'referenceFormationLibelle' => $rows[0]['formationLibelle'] ?? '—',
                'cells' => [],
            ];

            foreach ($rows as $row) {
                $matrixRow['cells'][$row['campagneId']][] = $row;
            }

            $matrixRows[] = $matrixRow;
        }

        usort($matrixRows, static function (array $rowA, array $rowB): int {
            return [$rowA['referenceFormationLibelle'], $rowA['referenceParcoursLibelle']] <=> [$rowB['referenceFormationLibelle'], $rowB['referenceParcoursLibelle']];
        });

        return $this->render('config/campagne_collecte/parcours_evolution.html.twig', [
            'campagnes' => array_values($campagnes),
            'matrixRows' => $matrixRows,
        ]);
    }
}
