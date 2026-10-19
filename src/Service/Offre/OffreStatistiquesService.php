<?php

namespace App\Service\Offre;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmission;
use App\Entity\TypeDiplome;
use App\Repository\AnneeRepository;
use App\Repository\ComposanteRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Repository\PlateformeAdmissionRepository;
use App\Repository\TypeDiplomeRepository;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class OffreStatistiquesService
{
    /** Couleurs prédéfinies pour les plateformes */
    private const PLATFORM_COLORS = [
        'PARCOURSUP' => '#2563EB',
        'PSUP' => '#2563EB',
        'MONMASTER' => '#7C3AED',
        'MMASTER' => '#7C3AED',
        'ECANDIDAT' => '#059669',
        'ECAND' => '#059669',
        'EEF' => '#D97706',
        'ETUDESENFRANCE' => '#D97706',
    ];

    /** Palette harmonieuse pour les graphiques */
    private const PALETTE = [
        '#0284C7', '#6366F1', '#8B5CF6', '#EC4899', '#F43F5E',
        '#F59E0B', '#10B981', '#14B8A6', '#06B6D4', '#3B82F6',
        '#84CC16', '#A855F7', '#64748B',
    ];

    public function __construct(
        private readonly FormationRepository                      $formationRepository,
        private readonly DpeParcoursRepository                    $dpeParcoursRepository,
        private readonly AnneeRepository                          $anneeRepository,
        private readonly PlateformeAdmissionRepository            $plateformeAdmissionRepository,
        private readonly PlateformeAdmissionParametreRepository   $plateformeParamRepository,
        private readonly ComposanteRepository                     $composanteRepository,
        private readonly TypeDiplomeRepository                    $typeDiplomeRepository,
        private readonly ChartBuilderInterface                    $chartBuilder,
    ) {
    }

    /**
     * Calcule toutes les statistiques agrégées de l'offre de formation pour la campagne donnée.
     *
     * @param array{
     *     comp?: int|string|null,
     *     type?: int|string|null,
     *     plateforme?: int|string|null,
     *     statut?: string|null,
     *     q?: string|null
     * } $filters
     *
     * @return array<string, mixed>
     */
    public function getStats(
        CampagneCollecte $campagne,
        ?Composante      $currentComposante = null,
        array            $filters = [],
    ): array {
        $filterCompId = !empty($filters['comp']) ? (int)$filters['comp'] : ($currentComposante?->getId());
        $filterTypeId = !empty($filters['type']) ? (int)$filters['type'] : null;
        $filterPlatId = !empty($filters['plateforme']) ? (int)$filters['plateforme'] : null;
        $filterStatut = !empty($filters['statut']) ? (string)$filters['statut'] : 'ouvert';
        $searchQuery  = !empty($filters['q']) ? mb_strtolower(trim((string)$filters['q'])) : null;

        // 1. Récupération optimisée par batch
        $formations = $this->formationRepository->findByCampagneWithRelations($campagne, $currentComposante);
        $allDpeParcours = $this->dpeParcoursRepository->findByCampagneCollecte($campagne, $currentComposante);
        $anneesByParcours = $this->anneeRepository->findByCampagneIndexedByParcours($campagne, $currentComposante);
        $paramsByAnnee = $this->plateformeParamRepository->findByCampagneIndexedByAnnee($campagne, $currentComposante);
        $allPlateformes = $this->plateformeAdmissionRepository->findBy(['active' => true], ['libelle' => 'ASC']);
        $allComposantes = $this->composanteRepository->findBy([], ['libelle' => 'ASC']);
        $allTypesDiplome = $this->typeDiplomeRepository->findBy([], ['libelle' => 'ASC']);

        // Indexation des DpeParcours par ID de parcours
        $dpeByParcours = [];
        foreach ($allDpeParcours as $dp) {
            $p = $dp->getParcours();
            if ($p !== null) {
                $dpeByParcours[$p->getId()] = $dp;
            }
        }

        // 2. Initialisation des structures d'agrégation
        $parNiveau = [];
        $parPlateforme = [];
        foreach ($allPlateformes as $plat) {
            $platCode = strtoupper(trim($plat->getCode()));
            $parPlateforme[$plat->getId()] = [
                'id' => $plat->getId(),
                'code' => $platCode,
                'libelle' => $plat->getLibelle(),
                'color' => $plat->getColor() ?: 'primary',
                'capaciteGlobale' => 0,
                'capaciteFi' => 0,
                'capaciteAlternance' => 0,
                'capaciteSpecifique' => 0,
                'nbAnnees' => 0,
                'formationsIds' => [],
                'parcoursIds' => [],
            ];
        }

        $parTypeDiplome = [];
        $parComposante = [];
        $detailLignes = [];

        $totalFormationsCount = 0;
        $totalParcoursCount = 0;
        $totalParcoursOuvertsCount = 0;
        $totalCapaciteGlobale = 0;
        $totalCapaciteNiveau1 = 0;
        $totalCapaciteMaster = 0;
        $totalCapaciteAlternance = 0;
        $formationsTraiteesIds = [];

        // 3. Parcours et agrégation des formations
        foreach ($formations as $formation) {
            $fComp = $formation->getComposantePorteuse();
            $fCompId = $fComp?->getId() ?? 0;
            $fType = $formation->getTypeDiplome();
            $fTypeId = $fType?->getId() ?? 0;

            // Filtres niveau formation
            if ($filterCompId !== null && $fCompId !== $filterCompId) {
                continue;
            }
            if ($filterTypeId !== null && $fTypeId !== $filterTypeId) {
                continue;
            }

            $fLibelleLong = mb_strtolower((string)($formation->getDisplayLong() ?? $formation->getDisplay() ?? ''));
            $matchesFormationSearch = ($searchQuery === null || str_contains($fLibelleLong, $searchQuery));

            $formationHasMatchingParcours = false;
            $tcAnneesCounted = [];
            $tcPlateformesCounted = []; // Clé : "ordre_platId"

            foreach ($formation->getParcours() as $parcours) {
                $pId = $parcours->getId();
                $dpe = $dpeByParcours[$pId] ?? null;
                if ($dpe === null) {
                    continue;
                }

                $isParcoursOuvert = $dpe->isOuvert();

                // Filtre sur le statut
                if ($filterStatut === 'ouvert' && !$isParcoursOuvert) {
                    continue;
                }
                if ($filterStatut === 'non_ouvert' && $isParcoursOuvert) {
                    continue;
                }

                // Filtre recherche sur le parcours si la formation ne matchait pas
                $pLibelle = mb_strtolower((string)$parcours->getLibelle());
                if ($searchQuery !== null && !$matchesFormationSearch && !str_contains($pLibelle, $searchQuery)) {
                    continue;
                }

                $annees = $anneesByParcours[$pId] ?? [];
                if (empty($annees)) {
                    continue;
                }

                // Filtre par plateforme
                if ($filterPlatId !== null) {
                    $hasPlat = false;
                    foreach ($annees as $an) {
                        foreach ($paramsByAnnee[$an->getId()] ?? [] as $param) {
                            if ($param->isActive() && $param->getPlateforme()?->getId() === $filterPlatId) {
                                $hasPlat = true;
                                break 2;
                            }
                        }
                    }
                    if (!$hasPlat) {
                        continue;
                    }
                }

                $formationHasMatchingParcours = true;
                $totalParcoursCount++;
                if ($isParcoursOuvert) {
                    $totalParcoursOuvertsCount++;
                }

                // Parcours des années du parcours
                foreach ($annees as $annee) {
                    $ordre = $annee->getOrdre();
                    if ($ordre === null) {
                        continue;
                    }

                    $isAnneeOuverte = $annee->isOuvert();
                    if ($filterStatut === 'ouvert' && !$isAnneeOuverte) {
                        continue;
                    }

                    $capaciteBrute = $annee->getCapaciteAccueil();
                    $isRecrutement = $annee->isProposeRecrutement();
                    $isTc = $formation->isAnneeTroncCommun($ordre);

                    // Dédoublonnage du Tronc Commun pour la capacité d'accueil
                    $capaciteComptee = $capaciteBrute;
                    if ($isTc) {
                        if (isset($tcAnneesCounted[$ordre])) {
                            $capaciteComptee = 0;
                        } else {
                            $tcAnneesCounted[$ordre] = true;
                        }
                    }

                    // Résolution du niveau (ex. BUT 1, L1, M1...)
                    $niveauInfo = $this->resolveNiveauInfo($fType, $ordre);
                    $niveauCode = $niveauInfo['code'];

                    // A. Agrégation par Niveau
                    if (!isset($parNiveau[$niveauCode])) {
                        $parNiveau[$niveauCode] = [
                            'code' => $niveauCode,
                            'libelle' => $niveauInfo['libelle'],
                            'cycle' => $niveauInfo['cycle'],
                            'sortKey' => $niveauInfo['sortKey'],
                            'ordre' => $ordre,
                            'typeDiplomeLibelle' => $fType?->getLibelle() ?? 'Autre',
                            'typeDiplomeLibelleCourt' => $fType?->getLibelleCourt() ?? 'Autre',
                            'capaciteTotale' => 0,
                            'capaciteRecrutement' => 0,
                            'nbFormations' => 0,
                            'nbParcours' => 0,
                            'formationsIds' => [],
                            'parcoursIds' => [],
                            'plateformes' => [], // [platCode => places]
                        ];
                    }
                    $parNiveau[$niveauCode]['capaciteTotale'] += $capaciteComptee;
                    if ($isRecrutement) {
                        $parNiveau[$niveauCode]['capaciteRecrutement'] += $capaciteComptee;
                    }
                    $parNiveau[$niveauCode]['formationsIds'][$formation->getId()] = true;
                    $parNiveau[$niveauCode]['parcoursIds'][$pId] = true;

                    // B. Agrégation par Type de Diplôme
                    if (!isset($parTypeDiplome[$fTypeId])) {
                        $parTypeDiplome[$fTypeId] = [
                            'id' => $fTypeId,
                            'libelle' => $fType?->getLibelle() ?? 'Sans type',
                            'libelleCourt' => $fType?->getLibelleCourt() ?? 'Autre',
                            'capaciteTotale' => 0,
                            'capaciteNiveau1' => 0,
                            'nbFormations' => 0,
                            'nbParcours' => 0,
                            'formationsIds' => [],
                            'parcoursIds' => [],
                            'niveaux' => [],
                        ];
                    }
                    $parTypeDiplome[$fTypeId]['capaciteTotale'] += $capaciteComptee;
                    if ($ordre === 1) {
                        $parTypeDiplome[$fTypeId]['capaciteNiveau1'] += $capaciteComptee;
                    }
                    $parTypeDiplome[$fTypeId]['formationsIds'][$formation->getId()] = true;
                    $parTypeDiplome[$fTypeId]['parcoursIds'][$pId] = true;
                    $parTypeDiplome[$fTypeId]['niveaux'][$niveauCode] = ($parTypeDiplome[$fTypeId]['niveaux'][$niveauCode] ?? 0) + $capaciteComptee;

                    // C. Agrégation par Composante
                    if (!isset($parComposante[$fCompId])) {
                        $parComposante[$fCompId] = [
                            'id' => $fCompId,
                            'libelle' => $fComp?->getLibelle() ?? 'Sans composante',
                            'libelleCourt' => $fComp?->getLibelle(),
                            'capaciteTotale' => 0,
                            'capaciteNiveau1' => 0,
                            'capaciteMaster' => 0,
                            'capaciteAlternance' => 0,
                            'nbFormations' => 0,
                            'nbParcours' => 0,
                            'formationsIds' => [],
                            'parcoursIds' => [],
                        ];
                    }
                    $parComposante[$fCompId]['capaciteTotale'] += $capaciteComptee;
                    if ($ordre === 1) {
                        $parComposante[$fCompId]['capaciteNiveau1'] += $capaciteComptee;
                    }
                    if ($niveauInfo['cycle'] === 'Master') {
                        $parComposante[$fCompId]['capaciteMaster'] += $capaciteComptee;
                    }
                    $parComposante[$fCompId]['formationsIds'][$formation->getId()] = true;
                    $parComposante[$fCompId]['parcoursIds'][$pId] = true;

                    // D. Agrégation Plateformes d'admission
                    $anneePlatCodes = [];
                    foreach ($paramsByAnnee[$annee->getId()] ?? [] as $param) {
                        $plat = $param->getPlateforme();
                        if (!$param->isActive() || $plat === null || !$plat->getActive()) {
                            continue;
                        }
                        $platId = $plat->getId();
                        $platCode = strtoupper(trim($plat->getCode()));
                        $anneePlatCodes[] = $platCode;

                        $tcPlatKey = $ordre . '_' . $platId;
                        $pCapGlobale = (int)($param->getCapaciteGlobale() ?? 0);
                        $pCapFi = (int)($param->getCapaciteFi() ?? 0);
                        $pCapAlt = (int)($param->getCapaciteAlternance() ?? 0);
                        $pCapSpec = (int)($param->getCapaciteSpecifique() ?? 0);

                        if ($isTc) {
                            if (isset($tcPlateformesCounted[$tcPlatKey])) {
                                $pCapGlobale = 0;
                                $pCapFi = 0;
                                $pCapAlt = 0;
                                $pCapSpec = 0;
                            } else {
                                $tcPlateformesCounted[$tcPlatKey] = true;
                            }
                        }

                        if (isset($parPlateforme[$platId])) {
                            $parPlateforme[$platId]['capaciteGlobale'] += $pCapGlobale;
                            $parPlateforme[$platId]['capaciteFi'] += $pCapFi;
                            $parPlateforme[$platId]['capaciteAlternance'] += $pCapAlt;
                            $parPlateforme[$platId]['capaciteSpecifique'] += $pCapSpec;
                            $parPlateforme[$platId]['nbAnnees']++;
                            $parPlateforme[$platId]['formationsIds'][$formation->getId()] = true;
                            $parPlateforme[$platId]['parcoursIds'][$pId] = true;
                        }

                        $parNiveau[$niveauCode]['plateformes'][$platCode] = ($parNiveau[$niveauCode]['plateformes'][$platCode] ?? 0) + $pCapGlobale;
                        $parComposante[$fCompId]['capaciteAlternance'] += $pCapAlt;
                        $totalCapaciteAlternance += $pCapAlt;
                    }

                    // Ligne pour la table détaillée et export CSV
                    $detailLignes[] = [
                        'composante' => $fComp?->getLibelle() ?? 'Sans composante',
                        'typeDiplome' => $fType?->getLibelle() ?? 'Autre',
                        'formation' => $formation->getDisplayLong() ?? $formation->getDisplay(),
                        'parcours' => $parcours->getLibelle(),
                        'isOuvert' => $isParcoursOuvert,
                        'isTc' => $isTc,
                        'niveau' => $niveauCode,
                        'ordre' => $ordre,
                        'capaciteAccueil' => $capaciteBrute,
                        'capaciteComptee' => $capaciteComptee,
                        'isRecrutement' => $isRecrutement,
                        'plateformes' => implode(', ', array_unique($anneePlatCodes)),
                    ];

                    $totalCapaciteGlobale += $capaciteComptee;
                    if ($ordre === 1) {
                        $totalCapaciteNiveau1 += $capaciteComptee;
                    }
                    if ($niveauInfo['cycle'] === 'Master') {
                        $totalCapaciteMaster += $capaciteComptee;
                    }
                }
            }

            if ($formationHasMatchingParcours) {
                $totalFormationsCount++;
                $formationsTraiteesIds[$formation->getId()] = true;
            }
        }

        // Finalisation des compteurs et pourcentages par niveau
        foreach ($parNiveau as &$item) {
            $item['nbFormations'] = count($item['formationsIds']);
            $item['nbParcours'] = count($item['parcoursIds']);
            $item['pourcentage'] = $totalCapaciteGlobale > 0 ? round(($item['capaciteTotale'] / $totalCapaciteGlobale) * 100, 1) : 0;
            unset($item['formationsIds'], $item['parcoursIds']);
        }
        unset($item);
        uasort($parNiveau, static fn(array $a, array $b): int => strcmp($a['sortKey'], $b['sortKey']));

        // Finalisation types de diplôme
        foreach ($parTypeDiplome as &$item) {
            $item['nbFormations'] = count($item['formationsIds']);
            $item['nbParcours'] = count($item['parcoursIds']);
            $item['pourcentage'] = $totalCapaciteGlobale > 0 ? round(($item['capaciteTotale'] / $totalCapaciteGlobale) * 100, 1) : 0;
            unset($item['formationsIds'], $item['parcoursIds']);
        }
        unset($item);
        uasort($parTypeDiplome, static fn(array $a, array $b): int => $b['capaciteTotale'] <=> $a['capaciteTotale']);

        // Finalisation composantes
        foreach ($parComposante as &$item) {
            $item['nbFormations'] = count($item['formationsIds']);
            $item['nbParcours'] = count($item['parcoursIds']);
            $item['pourcentage'] = $totalCapaciteGlobale > 0 ? round(($item['capaciteTotale'] / $totalCapaciteGlobale) * 100, 1) : 0;
            unset($item['formationsIds'], $item['parcoursIds']);
        }
        unset($item);
        uasort($parComposante, static fn(array $a, array $b): int => $b['capaciteTotale'] <=> $a['capaciteTotale']);

        // Finalisation plateformes
        foreach ($parPlateforme as &$item) {
            $item['nbFormations'] = count($item['formationsIds']);
            $item['nbParcours'] = count($item['parcoursIds']);
            $item['pourcentage'] = $totalCapaciteGlobale > 0 ? round(($item['capaciteGlobale'] / $totalCapaciteGlobale) * 100, 1) : 0;
            unset($item['formationsIds'], $item['parcoursIds']);
        }
        unset($item);
        uasort($parPlateforme, static fn(array $a, array $b): int => $b['capaciteGlobale'] <=> $a['capaciteGlobale']);

        // 4. Construction des graphiques Chart.js
        $charts = $this->buildCharts($parNiveau, $parPlateforme, $parTypeDiplome, $parComposante);

        return [
            'campagne' => $campagne,
            'composante' => $currentComposante,
            'filters' => [
                'comp' => $filterCompId,
                'type' => $filterTypeId,
                'plateforme' => $filterPlatId,
                'statut' => $filterStatut,
                'q' => $filters['q'] ?? '',
            ],
            'kpi' => [
                'totalCapacite' => $totalCapaciteGlobale,
                'totalNiveau1' => $totalCapaciteNiveau1,
                'totalMaster' => $totalCapaciteMaster,
                'totalAlternance' => $totalCapaciteAlternance,
                'totalFormations' => $totalFormationsCount,
                'totalParcours' => $totalParcoursCount,
                'totalParcoursOuverts' => $totalParcoursOuvertsCount,
            ],
            'parNiveau' => $parNiveau,
            'parPlateforme' => $parPlateforme,
            'parTypeDiplome' => $parTypeDiplome,
            'parComposante' => $parComposante,
            'charts' => $charts,
            'detailLignes' => $detailLignes,
            'allComposantes' => $allComposantes,
            'allTypesDiplome' => $allTypesDiplome,
            'allPlateformes' => $allPlateformes,
        ];
    }

    /**
     * Résout le code et l'intitulé de niveau en fonction du type de diplôme et de l'ordre d'année.
     * Exemples : L1, L2, L3, BUT 1, BUT 2, BUT 3, M1, M2, LP, etc.
     *
     * @return array{code: string, libelle: string, cycle: string, sortKey: string}
     */
    public function resolveNiveauInfo(?TypeDiplome $typeDiplome, int $ordre): array
    {
        $court = strtoupper(trim((string)$typeDiplome?->getLibelleCourt()));
        $libelleDiplome = $typeDiplome?->getLibelle() ?? 'Diplôme';

        if (in_array($court, ['LICENCE', 'L'], true)) {
            return [
                'code' => 'L' . $ordre,
                'libelle' => 'Licence ' . $ordre . ($ordre === 1 ? 'ère' : 'ème') . ' année',
                'cycle' => 'Licence',
                'sortKey' => sprintf('10_L_%02d', $ordre),
            ];
        }

        if ($court === 'BUT') {
            return [
                'code' => 'BUT ' . $ordre,
                'libelle' => 'Bachelor Univ. Tech. ' . $ordre . ($ordre === 1 ? 'ère' : 'ème') . ' année',
                'cycle' => 'BUT',
                'sortKey' => sprintf('20_BUT_%02d', $ordre),
            ];
        }

        if (in_array($court, ['LP', 'LICENCE PRO', 'LICENCE PROFESSIONNELLE'], true)) {
            return [
                'code' => 'LP',
                'libelle' => 'Licence Professionnelle',
                'cycle' => 'Licence Pro',
                'sortKey' => sprintf('30_LP_%02d', $ordre),
            ];
        }

        if (in_array($court, ['MASTER', 'M'], true)) {
            return [
                'code' => 'M' . $ordre,
                'libelle' => 'Master ' . $ordre . ($ordre === 1 ? 'ère' : 'ème') . ' année',
                'cycle' => 'Master',
                'sortKey' => sprintf('40_M_%02d', $ordre),
            ];
        }

        if ($court === 'DEUST') {
            return [
                'code' => 'DEUST ' . $ordre,
                'libelle' => 'DEUST ' . $ordre . ($ordre === 1 ? 'ère' : 'ème') . ' année',
                'cycle' => 'DEUST',
                'sortKey' => sprintf('50_DEUST_%02d', $ordre),
            ];
        }

        if ($court === 'DAEU') {
            return [
                'code' => 'DAEU',
                'libelle' => 'DAEU',
                'cycle' => 'DAEU',
                'sortKey' => sprintf('60_DAEU_%02d', $ordre),
            ];
        }

        $code = ($court !== '' ? $court : 'Année') . ' ' . $ordre;

        return [
            'code' => $code,
            'libelle' => $libelleDiplome . ' (année ' . $ordre . ')',
            'cycle' => $court !== '' ? $court : 'Autre',
            'sortKey' => sprintf('90_%s_%02d', $court ?: 'AUTRE', $ordre),
        ];
    }

    /**
     * Construit les graphiques interactifs Chart.js.
     *
     * @param array<string, array<string, mixed>> $parNiveau
     * @param array<int, array<string, mixed>>    $parPlateforme
     * @param array<int, array<string, mixed>>    $parTypeDiplome
     * @param array<int, array<string, mixed>>    $parComposante
     *
     * @return array<string, Chart>
     */
    private function buildCharts(
        array $parNiveau,
        array $parPlateforme,
        array $parTypeDiplome,
        array $parComposante,
    ): array {
        // 1. Graphique par Niveau (L1, BUT 1, M1...)
        $chartNiveaux = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $niveauxLabels = [];
        $niveauxData = [];
        $niveauxColors = [];

        foreach ($parNiveau as $niveau) {
            $niveauxLabels[] = $niveau['code'];
            $niveauxData[] = $niveau['capaciteTotale'];
            $niveauxColors[] = match ($niveau['cycle']) {
                'Licence' => '#0284C7',
                'BUT' => '#6366F1',
                'Licence Pro' => '#F59E0B',
                'Master' => '#EC4899',
                'DEUST' => '#10B981',
                'DAEU' => '#8B5CF6',
                default => '#64748B',
            };
        }

        $chartNiveaux->setData([
            'labels' => $niveauxLabels,
            'datasets' => [[
                'label' => 'Capacité d\'accueil',
                'data' => $niveauxData,
                'backgroundColor' => $niveauxColors,
                'borderRadius' => 6,
                'borderSkipped' => 'start',
            ]],
        ]);
        $chartNiveaux->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(ctx) { return " " + ctx.raw.toLocaleString("fr-FR") + " places"; }',
                    ],
                ],
            ],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ]);

        // 2. Graphique par Plateforme d'admission (Donut)
        $chartPlateformes = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $platLabels = [];
        $platData = [];
        $platColors = [];

        foreach ($parPlateforme as $plat) {
            if ($plat['capaciteGlobale'] > 0) {
                $platLabels[] = $plat['libelle'];
                $platData[] = $plat['capaciteGlobale'];
                $platColors[] = self::PLATFORM_COLORS[$plat['code']] ?? self::PALETTE[count($platColors) % count(self::PALETTE)];
            }
        }

        if (empty($platData)) {
            $platLabels = ['Aucune place déclarée'];
            $platData = [0];
            $platColors = ['#94A3B8'];
        }

        $chartPlateformes->setData([
            'labels' => $platLabels,
            'datasets' => [[
                'label' => 'Places déclarées',
                'data' => $platData,
                'backgroundColor' => $platColors,
                'hoverOffset' => 6,
            ]],
        ]);
        $chartPlateformes->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 12, 'padding' => 12]],
            ],
            'cutout' => '65%',
        ]);

        // 3. Graphique par Type de Diplôme (Barres horizontales)
        $chartTypesDiplome = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $tdLabels = [];
        $tdData = [];

        foreach ($parTypeDiplome as $td) {
            if ($td['capaciteTotale'] > 0) {
                $tdLabels[] = $td['libelleCourt'] ?: $td['libelle'];
                $tdData[] = $td['capaciteTotale'];
            }
        }

        $chartTypesDiplome->setData([
            'labels' => $tdLabels,
            'datasets' => [[
                'label' => 'Capacité totale',
                'data' => $tdData,
                'backgroundColor' => '#0284C7',
                'borderRadius' => 6,
            ]],
        ]);
        $chartTypesDiplome->setOptions([
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'y' => ['grid' => ['display' => false]],
            ],
        ]);

        // 4. Graphique par Composante (si applicable)
        $chartComposantes = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $compLabels = [];
        $compData = [];

        foreach ($parComposante as $comp) {
            if ($comp['capaciteTotale'] > 0) {
                $compLabels[] = $comp['libelleCourt'];
                $compData[] = $comp['capaciteTotale'];
            }
        }

        $chartComposantes->setData([
            'labels' => $compLabels,
            'datasets' => [[
                'label' => 'Capacité d\'accueil',
                'data' => $compData,
                'backgroundColor' => '#10B981',
                'borderRadius' => 6,
            ]],
        ]);
        $chartComposantes->setOptions([
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'y' => ['grid' => ['display' => false]],
            ],
        ]);

        return [
            'chartNiveaux' => $chartNiveaux,
            'chartPlateformes' => $chartPlateformes,
            'chartTypesDiplome' => $chartTypesDiplome,
            'chartComposantes' => $chartComposantes,
        ];
    }
}
