<?php

namespace App\Controller\Config;

use App\Entity\Parcours;
use App\Repository\CampagneCollecteRepository;
use App\Repository\ParcoursRepository;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        $data = $this->buildEvolutionMatrix($parcoursRepository, $campagneCollecteRepository);

        return $this->render('config/campagne_collecte/parcours_evolution.html.twig', [
            'campagnes' => $data['campagnes'],
            'matrixRows' => $data['matrixRows'],
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/parcours/evolution-campagnes/export-excel', name: 'app_campagne_collecte_parcours_evolution_export_excel', methods: ['GET'])]
    public function exportExcel(
        ParcoursRepository         $parcoursRepository,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response
    {
        $data = $this->buildEvolutionMatrix($parcoursRepository, $campagneCollecteRepository);
        $campagnes = $data['campagnes'];
        $matrixRows = $data['matrixRows'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Evolution parcours');

        $sheet->setCellValue('A1', 'Parcours origine');
        $sheet->setCellValue('B1', 'Formation origine');
        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:B2');

        $campaignWidth = 5;
        $columnIndex = 3;
        foreach ($campagnes as $campagne) {
            $startCol = Coordinate::stringFromColumnIndex($columnIndex);
            $endCol = Coordinate::stringFromColumnIndex($columnIndex + $campaignWidth - 1);
            $header = $campagne['libelle'];
            if (!empty($campagne['anneeUniversitaireLibelle'])) {
                $header .= ' - ' . $campagne['anneeUniversitaireLibelle'];
            }
            $sheet->setCellValue($startCol . '1', $header);
            $sheet->mergeCells($startCol . '1:' . $endCol . '1');
            $sheet->setCellValue($startCol . '2', 'Parcours');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1) . '2', 'Formation');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 2) . '2', 'Ouvert/Fermé');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 3) . '2', 'Alternance');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 4) . '2', 'Évolution');
            $columnIndex += $campaignWidth;
        }

        $rowIndex = 3;
        foreach ($matrixRows as $matrixRow) {
            $sheet->setCellValue('A' . $rowIndex, $matrixRow['referenceParcoursLibelle']);
            $sheet->setCellValue('B' . $rowIndex, $matrixRow['referenceFormationLibelle']);

            $columnIndex = 3;
            foreach ($campagnes as $campagne) {
                $entries = $matrixRow['cells'][$campagne['id']] ?? [];
                if (count($entries) === 0) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex, '—');
                    $columnIndex += $campaignWidth;
                    continue;
                }

                $parcoursValues = [];
                $formationValues = [];
                $openValues = [];
                $alternanceValues = [];
                $evolutionValues = [];

                foreach ($entries as $entry) {
                    $parcoursValues[] = (string)($entry['parcoursLibelle'] ?? '—');
                    $formationValues[] = (string)($entry['formationLibelle'] ?? '—');
                    $openValues[] = (string)($entry['openLabel'] ?? '—');
                    $alternanceValues[] = (string)($entry['alternanceLabel'] ?? '—');

                    $changes = $entry['changes'] ?? [];
                    if (count($changes) === 0) {
                        $evolutionValues[] = '—';
                    } else {
                        $evolutionValues[] = implode(' | ', $changes);
                    }
                }

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex, implode("\n", array_unique($parcoursValues)));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowIndex, implode("\n", array_unique($formationValues)));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 2) . $rowIndex, implode("\n", array_unique($openValues)));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 3) . $rowIndex, implode("\n", array_unique($alternanceValues)));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 4) . $rowIndex, implode("\n", array_unique($evolutionValues)));
                $columnIndex += $campaignWidth;
            }

            $rowIndex++;
        }

        $maxColumn = Coordinate::stringFromColumnIndex(max(2, $columnIndex - 1));
        $sheet->getStyle('A1:' . $maxColumn . '2')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $maxColumn . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A1:' . $maxColumn . max(2, $rowIndex - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A1:' . $maxColumn . '2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9ECEF');
        if ($rowIndex > 3) {
            $sheet->getStyle('A3:' . $maxColumn . ($rowIndex - 1))->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        }

        for ($i = 1; $i <= Coordinate::columnIndexFromString($maxColumn); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'oreof-parcours-evolution-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);
        $content = file_get_contents($tempFile);
        @unlink($tempFile);

        return new Response(
            $content ?: '',
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="parcours-evolution-campagnes.xlsx"',
            ]
        );
    }

    private function buildEvolutionMatrix(
        ParcoursRepository         $parcoursRepository,
        CampagneCollecteRepository $campagneCollecteRepository
    ): array
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

        return [
            'campagnes' => array_values($campagnes),
            'matrixRows' => $matrixRows,
        ];
    }
}
