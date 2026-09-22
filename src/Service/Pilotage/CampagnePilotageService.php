<?php

declare(strict_types=1);

namespace App\Service\Pilotage;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\DpeParcours;
use App\Entity\HistoriqueParcours;
use App\Repository\CampagneCollecteRepository;
use App\Repository\ComposanteRepository;
use App\Repository\DpeParcoursRepository;
use App\Workflow\Service\WorkflowExplorerService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CampagnePilotageService
{
    // Découpage des états du workflow dpeParcours en grandes phases métier
    public const PHASES = [
        'redaction' => [
            'label' => 'Rédaction & Préparation',
            'color' => 'warning',
            'badgeClass' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            'states' => ['initialisation_dpe', 'autorisation_saisie', 'en_cours_redaction', 'tacite_reconduction'],
        ],
        'locale' => [
            'label' => 'Validation Locale (DPE / Conseil)',
            'color' => 'info',
            'badgeClass' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
            'states' => ['soumis_parcours', 'soumis_dpe_composante', 'soumis_conseil'],
        ],
        'centrale' => [
            'label' => 'Validation Centrale (SES)',
            'color' => 'primary',
            'badgeClass' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300',
            'states' => ['soumis_central', 'soumis_central_sans_cfvu', 'en_cours_redaction_ss_cfvu'],
        ],
        'cfvu_publie' => [
            'label' => 'CFVU & Publication',
            'color' => 'success',
            'badgeClass' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
            'states' => ['soumis_cfvu', 'valide_cfvu', 'valide_a_publier', 'publie'],
        ],
        'reserves' => [
            'label' => 'Réserves & Rejets',
            'color' => 'danger',
            'badgeClass' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
            'states' => ['soumis_conseil_reserve', 'soumis_central_reserve_cfvu', 'soumis_dpe_composante_reserve_cfvu', 'non_ouverture', 'non_ouverture_ses'],
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DpeParcoursRepository $dpeParcoursRepository,
        private readonly ComposanteRepository $composanteRepository,
        private readonly CampagneCollecteRepository $campagneCollecteRepository,
        private readonly WorkflowExplorerService $workflowExplorerService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Analyse globale de la campagne (KPIs, répartition par phase, avancement).
     *
     * @return array<string, mixed>
     */
    public function getCampagneOverview(CampagneCollecte $campagne): array
    {
        $dpeParcoursList = $this->dpeParcoursRepository->findBy(['campagneCollecte' => $campagne]);
        $totalParcours = count($dpeParcoursList);

        $phaseCounts = [
            'redaction' => 0,
            'locale' => 0,
            'centrale' => 0,
            'cfvu_publie' => 0,
            'reserves' => 0,
        ];

        $stateCounts = [];
        $validatedCount = 0;

        foreach ($dpeParcoursList as $dpe) {
            $state = $this->extractPrimaryState($dpe);
            $stateCounts[$state] = ($stateCounts[$state] ?? 0) + 1;

            if (in_array($state, ['valide_cfvu', 'valide_a_publier', 'publie'], true)) {
                $validatedCount++;
            }

            $matched = false;
            foreach (self::PHASES as $phaseKey => $phaseDef) {
                if (in_array($state, $phaseDef['states'], true)) {
                    $phaseCounts[$phaseKey]++;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                // Si état non répertorié, rattacher par défaut à la phase de rédaction/initiale
                $phaseCounts['redaction']++;
            }
        }

        $globalProgressPercent = $totalParcours > 0 ? (int)round(($validatedCount / $totalParcours) * 100) : 0;

        return [
            'campagne' => $campagne,
            'totalParcours' => $totalParcours,
            'validatedCount' => $validatedCount,
            'globalProgressPercent' => $globalProgressPercent,
            'phaseCounts' => $phaseCounts,
            'stateCounts' => $stateCounts,
            'phasesDef' => self::PHASES,
        ];
    }

    /**
     * Matrice croisée d'avancement par Composante (1B).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getComposantesMatrix(CampagneCollecte $campagne): array
    {
        $composantes = $this->composanteRepository->findAll();
        $dpeParcoursList = $this->dpeParcoursRepository->findBy(['campagneCollecte' => $campagne]);

        // Grouper par composante
        $dpeByComposante = [];
        foreach ($dpeParcoursList as $dpe) {
            $composante = $dpe->getFormation()?->getComposantePorteuse();
            if ($composante !== null) {
                $dpeByComposante[$composante->getId()][] = $dpe;
            }
        }

        $matrix = [];
        foreach ($composantes as $composante) {
            $items = $dpeByComposante[$composante->getId()] ?? [];
            $totalComp = count($items);

            if ($totalComp === 0) {
                continue; // Ne pas encombrer avec les composantes sans offre sur la campagne
            }

            $phaseCounts = [
                'redaction' => 0,
                'locale' => 0,
                'centrale' => 0,
                'cfvu_publie' => 0,
                'reserves' => 0,
            ];

            $stateCounts = [];
            $validatedComp = 0;

            foreach ($items as $dpe) {
                $state = $this->extractPrimaryState($dpe);
                $stateCounts[$state] = ($stateCounts[$state] ?? 0) + 1;

                if (in_array($state, ['valide_cfvu', 'valide_a_publier', 'publie'], true)) {
                    $validatedComp++;
                }

                foreach (self::PHASES as $phaseKey => $phaseDef) {
                    if (in_array($state, $phaseDef['states'], true)) {
                        $phaseCounts[$phaseKey]++;
                        break;
                    }
                }
            }

            $progressPercent = $totalComp > 0 ? (int)round(($validatedComp / $totalComp) * 100) : 0;

            $statusVariant = match (true) {
                $progressPercent >= 80 => 'success',
                $progressPercent >= 40 => 'warning',
                default => 'danger',
            };

            $matrix[] = [
                'composante' => $composante,
                'totalParcours' => $totalComp,
                'validatedCount' => $validatedComp,
                'progressPercent' => $progressPercent,
                'statusVariant' => $statusVariant,
                'phaseCounts' => $phaseCounts,
                'stateCounts' => $stateCounts,
            ];
        }

        // Trier par taux d'avancement croissant (les composantes en retard en premier)
        usort($matrix, fn($a, $b) => $a['progressPercent'] <=> $b['progressPercent']);

        return $matrix;
    }

    /**
     * Analyse des temps de séjour et détection des dossiers stagnants (1A).
     *
     * @return array{stagnantDossiers: array, avgDurationsByState: array, stagnantCount: int}
     */
    public function getSlaAndStagnantDossiers(CampagneCollecte $campagne, int $stagnantThresholdDays = 15): array
    {
        $dpeParcoursList = $this->dpeParcoursRepository->findBy(['campagneCollecte' => $campagne]);
        $now = new DateTime();

        $stagnantDossiers = [];
        $durationsByState = [];

        // Précharger l'historique récent des parcours pour les calculs de date
        $historiqueRepo = $this->entityManager->getRepository(HistoriqueParcours::class);

        foreach ($dpeParcoursList as $dpe) {
            $state = $this->extractPrimaryState($dpe);

            // Ignorer les états terminaux
            if (in_array($state, ['publie', 'non_ouverture', 'non_ouverture_ses'], true)) {
                continue;
            }

            $parcours = $dpe->getParcours();
            if ($parcours === null) {
                continue;
            }

            // Récupérer le dernier événement d'historique pour ce parcours
            $lastHisto = $historiqueRepo->findOneBy(
                ['parcours' => $parcours],
                ['created' => 'DESC']
            );

            $lastDate = $lastHisto?->getCreated() ?? $dpe->getCreated() ?? $parcours->getUpdated() ?? $now;
            $diffDays = (int)$now->diff($lastDate)->format('%a');

            // Enregistrer pour le calcul de moyenne par état
            $durationsByState[$state][] = $diffDays;

            if ($diffDays >= $stagnantThresholdDays) {
                $formation = $dpe->getFormation();
                $composante = $formation?->getComposantePorteuse();

                $rfUser = $formation?->getResponsableMention();

                $stagnantDossiers[] = [
                    'dpe' => $dpe,
                    'parcours' => $parcours,
                    'formation' => $formation,
                    'composante' => $composante,
                    'state' => $state,
                    'stateLabel' => $this->translateState($state),
                    'daysInState' => $diffDays,
                    'lastUpdate' => $lastDate,
                    'responsable' => $rfUser,
                    'severity' => $diffDays >= 30 ? 'critical' : 'warning',
                ];
            }
        }

        // Trier les dossiers stagnants par nombre de jours décroissant (les plus critiques en haut)
        usort($stagnantDossiers, fn($a, $b) => $b['daysInState'] <=> $a['daysInState']);

        // Calculer les durées moyennes par état
        $avgDurationsByState = [];
        foreach ($durationsByState as $stateKey => $daysList) {
            $avgDurationsByState[$stateKey] = [
                'state' => $stateKey,
                'label' => $this->translateState($stateKey),
                'count' => count($daysList),
                'avgDays' => (int)round(array_sum($daysList) / count($daysList)),
                'maxDays' => max($daysList),
            ];
        }

        usort($avgDurationsByState, fn($a, $b) => $b['avgDays'] <=> $a['avgDays']);

        return [
            'stagnantDossiers' => $stagnantDossiers,
            'stagnantCount' => count($stagnantDossiers),
            'avgDurationsByState' => $avgDurationsByState,
            'thresholdDays' => $stagnantThresholdDays,
        ];
    }

    /**
     * Extrait le code de l'état principal d'un DPE (tableau JSON marking store ou string).
     */
    private function extractPrimaryState(DpeParcours $dpe): string
    {
        $etat = $dpe->getEtatValidation();

        if (is_string($etat)) {
            $decoded = json_decode($etat, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $key = array_key_first($decoded);
                return is_string($key) && !is_numeric($key) ? (string)$key : (string)$decoded[$key];
            }
            return $etat !== '' ? $etat : 'initialisation_dpe';
        }

        if (is_array($etat) && count($etat) > 0) {
            $key = array_key_first($etat);
            return is_string($key) && !is_numeric($key) ? (string)$key : (string)$etat[$key];
        }

        return 'initialisation_dpe';
    }

    private function translateState(string $state): string
    {
        $translated = $this->translator->trans('validation.' . $state, [], 'process');
        if ($translated === 'validation.' . $state) {
            $translated = $this->translator->trans($state, [], 'process');
        }

        if ($translated === $state || $translated === 'validation.' . $state) {
            return ucfirst(str_replace('_', ' ', $state));
        }

        return $translated;
    }
}
