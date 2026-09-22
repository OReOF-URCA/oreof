<?php

namespace App\Service\Parcours;

use App\Classes\GetDpeParcours;
use App\DTO\StructureParcours;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use App\Repository\ParcoursVersioningRepository;
use App\Service\VersioningParcours;
use App\TypeDiplome\TypeDiplomeResolver;
use Doctrine\ORM\EntityManagerInterface;

class ParcoursHoursComparator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersioningParcours $versioningParcours,
        private readonly ParcoursVersioningRepository $versioningRepository,
        private readonly TypeDiplomeResolver $typeDiplomeResolver
    ) {
    }

    /**
     * Récupère la liste des options de versions disponibles pour la comparaison.
     *
     * @return array<int, array{id: string, label: string, isDefault: bool}>
     */
    public function getAvailableReferences(Parcours $parcours): array
    {
        $options = [];

        // 1. Parcours N-1 si copie existante
        if ($parcours->getParcoursOrigineCopie() !== null) {
            $nMinus1 = $parcours->getParcoursOrigineCopie();
            $dpeparcoursPrec = GetDpeParcours::getFromParcours($nMinus1);
            $campagne = $dpeparcoursPrec?->getCampagneCollecte()?->getLibelle() ?? 'N-1';
            $options[] = [
                'id' => 'n-1',
                'label' => "Année précédente ({$campagne})",
                'isDefault' => true,
            ];
        }

        // 2. Versions archivées / CFVU
        $versions = $this->versioningRepository->findBy(
            ['parcours' => $parcours],
            ['version_timestamp' => 'DESC']
        );

        $hasDefault = count($options) > 0;
        foreach ($versions as $v) {
            $isCfvu = $v->isCvfuFlag() ? ' (CFVU)' : '';
            $date = $v->getVersionTimestamp()?->format('d/m/Y H:i') ?? 'Inconnue';
            $options[] = [
                'id' => 'ver_' . $v->getId(),
                'label' => "Version du {$date}{$isCfvu}",
                'isDefault' => !$hasDefault,
            ];
            $hasDefault = true;
        }

        return $options;
    }

    /**
     * Charge la structure de référence et extrait les données horaires indexées par EC.
     *
     * @return array{
     *     referenceLabel: string,
     *     ecs: array<string, array{cmPres: float, tdPres: float, tpPres: float, tePres: float, cmDist: float, tdDist: float, tpDist: float, totalPres: float, totalDist: float, totalGlobal: float}>,
     *     ues: array<string, array{totalPres: float, totalDist: float, totalGlobal: float}>,
     *     semestres: array<int, array{totalPres: float, totalDist: float, totalGlobal: float}>,
     *     parcoursTotal: array{totalPres: float, totalDist: float, totalGlobal: float, cmPres: float, tdPres: float, tpPres: float, tePres: float, cmDist: float, tdDist: float, tpDist: float}
     * }
     */
    public function getReferenceData(Parcours $parcours, ?string $referenceId = null): array
    {
        $emptyResult = [
            'referenceLabel' => 'Aucune version de référence',
            'ecs' => [],
            'ues' => [],
            'semestres' => [],
            'parcoursTotal' => [
                'totalPres' => 0.0,
                'totalDist' => 0.0,
                'totalGlobal' => 0.0,
                'cmPres' => 0.0,
                'tdPres' => 0.0,
                'tpPres' => 0.0,
                'tePres' => 0.0,
                'cmDist' => 0.0,
                'tdDist' => 0.0,
                'tpDist' => 0.0,
            ],
        ];

        $refDto = null;
        $refLabel = '';

        // Détection de la référence demandée
        if ($referenceId === 'n-1' || ($referenceId === null && $parcours->getParcoursOrigineCopie() !== null)) {
            $orig = $parcours->getParcoursOrigineCopie();
            if ($orig !== null) {
                $typeD = $this->typeDiplomeResolver->fromParcours($orig);
                $refDto = $typeD->calculStructureParcours($orig);
                $dpeOrig = GetDpeParcours::getFromParcours($orig);
                $campagne = $dpeOrig?->getCampagneCollecte()?->getLibelle() ?? 'N-1';
                $refLabel = "Année précédente ({$campagne})";
            }
        } elseif (str_starts_with((string)$referenceId, 'ver_')) {
            $vId = (int)substr((string)$referenceId, 4);
            /** @var ParcoursVersioning|null $version */
            $version = $this->versioningRepository->find($vId);
            if ($version !== null) {
                try {
                    $loaded = $this->versioningParcours->loadParcoursFromVersion($version);
                    $refDto = $loaded['dto'] ?? null;
                    $refLabel = "Version du " . ($version->getVersionTimestamp()?->format('d/m/Y H:i') ?? '');
                } catch (\Throwable) {
                    $refDto = null;
                }
            }
        } elseif ($referenceId === null) {
            // Pas de N-1 : essayer dernière version CFVU ou dernière version
            $lastCfvu = $this->versioningParcours->getLastCfvuVersion($parcours);
            if ($lastCfvu !== null) {
                try {
                    $loaded = $this->versioningParcours->loadParcoursFromVersion($lastCfvu);
                    $refDto = $loaded['dto'] ?? null;
                    $refLabel = "Dernière version CFVU (" . ($lastCfvu->getVersionTimestamp()?->format('d/m/Y') ?? '') . ")";
                } catch (\Throwable) {
                    $refDto = null;
                }
            }
        }

        if (!$refDto instanceof StructureParcours) {
            return $emptyResult;
        }

        return $this->extractDataFromStructureDto($refDto, $refLabel);
    }

    private function extractDataFromStructureDto(StructureParcours $dto, string $label): array
    {
        $ecs = [];
        $ues = [];
        $semestres = [];

        $pCmPres = 0.0;
        $pTdPres = 0.0;
        $pTpPres = 0.0;
        $pTePres = 0.0;
        $pCmDist = 0.0;
        $pTdDist = 0.0;
        $pTpDist = 0.0;
        $pTotalPres = 0.0;
        $pTotalDist = 0.0;
        $pTotalGlobal = 0.0;

        foreach ($dto->semestres as $semestreOrdre => $semestreDto) {
            $semPres = 0.0;
            $semDist = 0.0;
            $semGlobal = 0.0;

            foreach ($semestreDto->ues as $ueId => $ueDto) {
                $uePres = 0.0;
                $ueDist = 0.0;
                $ueGlobal = 0.0;

                $ueCode = $ueDto->ue?->getCodeApogee() ?? (string)$ueId;

                foreach ($ueDto->elementConstitutifs as $ecId => $ecDto) {
                    $elem = $ecDto->elementConstitutif;
                    $h = $ecDto->heuresEctsEc;

                    $cmP = (float)($h?->cmPres ?? 0);
                    $tdP = (float)($h?->tdPres ?? 0);
                    $tpP = (float)($h?->tpPres ?? 0);
                    $teP = (float)($h?->tePres ?? 0);

                    $cmD = (float)($h?->cmDist ?? 0);
                    $tdD = (float)($h?->tdDist ?? 0);
                    $tpD = (float)($h?->tpDist ?? 0);

                    $totP = $cmP + $tdP + $tpP;
                    $totD = $cmD + $tdD + $tpD;
                    $totG = $totP + $totD;

                    $ects = (float)($h?->ects ?? $elem?->getEcts() ?? 0);

                    $ecData = [
                        'ects' => $ects,
                        'cmPres' => $cmP,
                        'tdPres' => $tdP,
                        'tpPres' => $tpP,
                        'tePres' => $teP,
                        'cmDist' => $cmD,
                        'tdDist' => $tdD,
                        'tpDist' => $tpD,
                        'totalPres' => $totP,
                        'totalDist' => $totD,
                        'totalGlobal' => $totG,
                    ];

                    // Indexer par ID, par Code et par Libellé pour assurer le matching même si l'ID a changé lors d'une copie
                    if ($elem?->getId()) {
                        $ecs['id_' . $elem->getId()] = $ecData;
                    }
                    if ($elem?->getCode()) {
                        $ecs['code_' . trim(strtoupper($elem->getCode()))] = $ecData;
                    }
                    if ($elem?->getLibelle()) {
                        $ecs['libelle_' . trim(mb_strtolower($elem->getLibelle()))] = $ecData;
                    }

                    $uePres += $totP;
                    $ueDist += $totD;
                    $ueGlobal += $totG;

                    // Si EC enfants (EC à choix)
                    foreach ($ecDto->elementsConstitutifsEnfants as $eceDto) {
                        $eceElem = $eceDto->elementConstitutif;
                        $eceH = $eceDto->heuresEctsEc;

                        $eceCmP = (float)($eceH?->cmPres ?? 0);
                        $eceTdP = (float)($eceH?->tdPres ?? 0);
                        $eceTpP = (float)($eceH?->tpPres ?? 0);
                        $eceTeP = (float)($eceH?->tePres ?? 0);
                        $eceCmD = (float)($eceH?->cmDist ?? 0);
                        $eceTdD = (float)($eceH?->tdDist ?? 0);
                        $eceTpD = (float)($eceH?->tpDist ?? 0);
                        $eceEcts = (float)($eceH?->ects ?? $eceElem?->getEcts() ?? 0);

                        $eceTotP = $eceCmP + $eceTdP + $eceTpP;
                        $eceTotD = $eceCmD + $eceTdD + $eceTpD;
                        $eceTotG = $eceTotP + $eceTotD;

                        $eceData = [
                            'ects' => $eceEcts,
                            'cmPres' => $eceCmP,
                            'tdPres' => $eceTdP,
                            'tpPres' => $eceTpP,
                            'tePres' => $eceTeP,
                            'cmDist' => $eceCmD,
                            'tdDist' => $eceTdD,
                            'tpDist' => $eceTpD,
                            'totalPres' => $eceTotP,
                            'totalDist' => $eceTotD,
                            'totalGlobal' => $eceTotG,
                        ];

                        if ($eceElem?->getId()) {
                            $ecs['id_' . $eceElem->getId()] = $eceData;
                        }
                        if ($eceElem?->getCode()) {
                            $ecs['code_' . trim(strtoupper($eceElem->getCode()))] = $eceData;
                        }
                        if ($eceElem?->getLibelle()) {
                            $ecs['libelle_' . trim(mb_strtolower($eceElem->getLibelle()))] = $eceData;
                        }
                    }

                    $pCmPres += $cmP;
                    $pTdPres += $tdP;
                    $pTpPres += $tpP;
                    $pTePres += $teP;
                    $pCmDist += $cmD;
                    $pTdDist += $tdD;
                    $pTpDist += $tpD;
                }

                $ues[(string)$ueId] = [
                    'totalPres' => $uePres,
                    'totalDist' => $ueDist,
                    'totalGlobal' => $ueGlobal,
                ];
                $ues['code_' . trim(strtoupper($ueCode))] = [
                    'totalPres' => $uePres,
                    'totalDist' => $ueDist,
                    'totalGlobal' => $ueGlobal,
                ];

                $semPres += $uePres;
                $semDist += $ueDist;
                $semGlobal += $ueGlobal;
            }

            $semestres[(int)$semestreOrdre] = [
                'totalPres' => $semPres,
                'totalDist' => $semDist,
                'totalGlobal' => $semGlobal,
            ];

            $pTotalPres += $semPres;
            $pTotalDist += $semDist;
            $pTotalGlobal += $semGlobal;
        }

        return [
            'referenceLabel' => $label,
            'ecs' => $ecs,
            'ues' => $ues,
            'semestres' => $semestres,
            'parcoursTotal' => [
                'totalPres' => $pTotalPres,
                'totalDist' => $pTotalDist,
                'totalGlobal' => $pTotalGlobal,
                'cmPres' => $pCmPres,
                'tdPres' => $pTdPres,
                'tpPres' => $pTpPres,
                'tePres' => $pTePres,
                'cmDist' => $pCmDist,
                'tdDist' => $pTdDist,
                'tpDist' => $pTpDist,
            ],
        ];
    }
}
