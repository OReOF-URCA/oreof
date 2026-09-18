<?php

declare(strict_types=1);

namespace App\Workflow\Service;

use App\Entity\ChangeRf;
use App\Entity\DpeFormation;
use App\Entity\DpeParcours;
use App\Entity\FicheMatiere;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Contracts\Translation\TranslatorInterface;

final class WorkflowExplorerService
{
    private const WORKFLOW_CONFIGS = [
        'dpeParcours' => [
            'label' => 'Validation des Parcours (DPE)',
            'description' => 'Cycle de validation complet des maquettes de parcours (initialisation, soumission RF, DPE, Conseil, SES, CFVU, publication).',
            'entityClass' => DpeParcours::class,
            'stateProperty' => 'etatValidation',
            'badge' => 'primary',
            'icon' => 'ph:path',
        ],
        'fiche' => [
            'label' => 'Fiches Matières / EC',
            'description' => 'Cycle de validation des fiches matières et éléments constitutifs (rédaction, soumission, validation centrale).',
            'entityClass' => FicheMatiere::class,
            'stateProperty' => 'etatFiche',
            'badge' => 'success',
            'icon' => 'ph:file-text',
        ],
        'changeRf' => [
            'label' => 'Changement de Responsable de Formation',
            'description' => 'Procédure d\'approbation pour le changement de responsable de formation (Conseil, SES, CFVU avec ou sans PV).',
            'entityClass' => ChangeRf::class,
            'stateProperty' => 'etatDemande',
            'badge' => 'warning',
            'icon' => 'ph:user-switch',
        ],
        'dpeFormation' => [
            'label' => 'Validation des Formations (DPE)',
            'description' => 'Workflow global de validation au niveau de la mention/formation.',
            'entityClass' => DpeFormation::class,
            'stateProperty' => 'etatValidation',
            'badge' => 'info',
            'icon' => 'ph:graduation-cap',
        ],
    ];

    public function __construct(
        private readonly Registry $workflowRegistry,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, array{label: string, description: string, badge: string, icon: string, entityClass: string}>
     */
    public function getAvailableWorkflows(): array
    {
        return self::WORKFLOW_CONFIGS;
    }

    /**
     * Retourne toutes les données consolidées d'un workflow pour l'interface admin.
     *
     * @return array<string, mixed>
     */
    public function getWorkflowData(string $workflowName = 'dpeParcours'): array
    {
        if (!isset(self::WORKFLOW_CONFIGS[$workflowName])) {
            $workflowName = 'dpeParcours';
        }

        $config = self::WORKFLOW_CONFIGS[$workflowName];
        $dummyEntity = new ($config['entityClass'])();
        $workflow = $this->workflowRegistry->get($dummyEntity, $workflowName);

        $definition = $workflow->getDefinition();
        $metadataStore = $workflow->getMetadataStore();

        $initialMarking = $definition->getInitialPlaces()[0] ?? null;

        // Récupération du décompte des entités par état en BDD
        $countsByState = $this->getEntityCountsByState($config['entityClass'], $config['stateProperty']);

        // 1. Extraction des Places (États)
        $places = [];
        $placesMap = [];
        foreach ($definition->getPlaces() as $placeName => $placeValue) {
            // Dans Symfony workflow definition, les places peuvent être clé ou valeur
            $pName = is_string($placeName) && !is_numeric($placeName) ? $placeName : (string)$placeValue;
            $rawMeta = $metadataStore->getPlaceMetadata($pName);

            $labelKey = $rawMeta['label'] ?? $pName;
            $translatedLabel = $this->translateKey($labelKey, 'process');

            $color = $rawMeta['color'] ?? $this->deducePlaceColor($pName);
            $icon = $rawMeta['icon'] ?? $this->deducePlaceIcon($pName);
            $process = (bool)($rawMeta['process'] ?? false);
            $isTimeline = (bool)($rawMeta['isTimeline'] ?? false);
            $isInitial = ($pName === $initialMarking);
            $count = $countsByState[$pName] ?? 0;

            $placeData = [
                'id' => $pName,
                'name' => $pName,
                'label' => $translatedLabel,
                'rawLabel' => $labelKey,
                'color' => $color,
                'tailwindBg' => $this->resolveTailwindBg($color),
                'tailwindBorder' => $this->resolveTailwindBorder($color),
                'tailwindText' => $this->resolveTailwindText($color),
                'icon' => $icon,
                'process' => $process,
                'isTimeline' => $isTimeline,
                'isInitial' => $isInitial,
                'count' => $count,
                'metadata' => $rawMeta,
                'incomingTransitions' => [],
                'outgoingTransitions' => [],
            ];

            $places[$pName] = $placeData;
            $placesMap[$pName] = $placeData;
        }

        // 2. Extraction des Transitions
        $transitions = [];
        $edgeIndex = 0;
        foreach ($definition->getTransitions() as $transition) {
            $tName = $transition->getName();
            $froms = $transition->getFroms();
            $tos = $transition->getTos();
            $rawMeta = $metadataStore->getTransitionMetadata($transition);

            $type = $rawMeta['type'] ?? $this->deduceTransitionType($tName);
            $buttonClass = $rawMeta['button_class'] ?? $this->resolveButtonClass($type, $rawMeta);
            $buttonIcon = $rawMeta['button_icon'] ?? ($rawMeta['icon'] ?? $this->deduceTransitionIcon($type));
            $title = $rawMeta['titre'] ?? ($rawMeta['label'] ?? $tName);
            $translatedTitle = $this->translateKey($title, 'process');

            $formType = null;
            $formShortName = null;
            if (isset($rawMeta['form']['type'])) {
                $formType = (string)$rawMeta['form']['type'];
                $parts = explode('\\', $formType);
                $formShortName = end($parts);
            }

            $recipients = $rawMeta['recipients'] ?? [];
            if (!is_array($recipients)) {
                $recipients = [$recipients];
            }

            $validationStep = $rawMeta['validation']['step'] ?? null;
            $guard = $rawMeta['guard'] ?? null;
            $operation = $rawMeta['operation'] ?? null;

            $transitionData = [
                'id' => $tName . '_' . $edgeIndex,
                'name' => $tName,
                'title' => $translatedTitle,
                'rawTitle' => $title,
                'from' => $froms,
                'to' => $tos,
                'type' => $type,
                'badgeClass' => $this->resolveTypeBadgeClass($type),
                'buttonClass' => $buttonClass,
                'buttonIcon' => $buttonIcon,
                'formType' => $formType,
                'formShortName' => $formShortName,
                'formFields' => $rawMeta['form']['fields'] ?? [],
                'recipients' => $recipients,
                'validationStep' => $validationStep,
                'guard' => $guard,
                'operation' => $operation,
                'requiresComment' => $rawMeta['requires_comment'] ?? false,
                'hasUpload' => $rawMeta['hasUpload'] ?? false,
                'hasDate' => $rawMeta['hasDate'] ?? false,
                'metadata' => $rawMeta,
            ];

            $transitions[] = $transitionData;
            $edgeIndex++;

            // Mettre à jour les références sur les places
            foreach ($froms as $fromPlace) {
                if (isset($places[$fromPlace])) {
                    $places[$fromPlace]['outgoingTransitions'][] = [
                        'transition' => $tName,
                        'to' => $tos,
                        'type' => $type,
                        'title' => $translatedTitle,
                    ];
                }
            }
            foreach ($tos as $toPlace) {
                if (isset($places[$toPlace])) {
                    $places[$toPlace]['incomingTransitions'][] = [
                        'transition' => $tName,
                        'from' => $froms,
                        'type' => $type,
                        'title' => $translatedTitle,
                    ];
                }
            }
        }

        // 3. Construction des éléments Cytoscape.js
        $cyElements = $this->buildCytoscapeElements($places, $transitions);

        // 4. Construction de la séquence "Happy Path" (Pipeline nominal)
        $pipeline = $this->buildHappyPathPipeline($places, $transitions, $initialMarking);

        $totalEntities = array_sum($countsByState);

        return [
            'key' => $workflowName,
            'config' => $config,
            'initialMarking' => $initialMarking,
            'places' => $places,
            'transitions' => $transitions,
            'cytoscapeElements' => $cyElements,
            'pipeline' => $pipeline,
            'stats' => [
                'totalPlaces' => count($places),
                'totalTransitions' => count($transitions),
                'totalEntities' => $totalEntities,
            ],
        ];
    }

    /**
     * Génère les nœuds et arêtes formatés pour Cytoscape.js.
     *
     * @param array<string, mixed> $places
     * @param array<int, mixed> $transitions
     * @return array{nodes: array, edges: array}
     */
    private function buildCytoscapeElements(array $places, array $transitions): array
    {
        $nodes = [];
        foreach ($places as $place) {
            $nodes[] = [
                'data' => [
                    'id' => $place['id'],
                    'label' => $place['label'],
                    'name' => $place['name'],
                    'color' => $place['color'],
                    'icon' => $place['icon'],
                    'count' => $place['count'],
                    'isInitial' => $place['isInitial'],
                    'isTimeline' => $place['isTimeline'],
                    'process' => $place['process'],
                    'bgColor' => $this->getHexColor($place['color']),
                    'borderColor' => $this->getHexBorderColor($place['color']),
                    'raw' => $place,
                ],
            ];
        }

        $edges = [];
        foreach ($transitions as $trans) {
            foreach ($trans['from'] as $source) {
                foreach ($trans['to'] as $target) {
                    $edgeId = 'e_' . $source . '_' . $trans['name'] . '_' . $target;
                    $edges[] = [
                        'data' => [
                            'id' => $edgeId,
                            'source' => $source,
                            'target' => $target,
                            'name' => $trans['name'],
                            'label' => $trans['title'] !== $trans['name'] ? $trans['title'] : $trans['name'],
                            'type' => $trans['type'],
                            'lineColor' => $this->getEdgeColor($trans['type']),
                            'lineStyle' => $this->getEdgeStyle($trans['type']),
                            'recipients' => $trans['recipients'],
                            'formShortName' => $trans['formShortName'],
                            'raw' => $trans,
                        ],
                    ];
                }
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Construit une vue séquentielle "Happy Path" (chaîne nominale de validation).
     */
    private function buildHappyPathPipeline(array $places, array $transitions, ?string $initialMarking): array
    {
        // On sélectionne en priorité les places avec isTimeline=true ou process=true
        $timelinePlaces = [];
        foreach ($places as $place) {
            if ($place['isTimeline'] || $place['process'] || $place['isInitial']) {
                $timelinePlaces[$place['id']] = $place;
            }
        }

        return array_values($timelinePlaces);
    }

    /**
     * Récupère le nombre d'entités par état en BDD via Doctrine.
     *
     * @return array<string, int>
     */
    private function getEntityCountsByState(string $entityClass, string $property): array
    {
        try {
            $tableName = $this->entityManager->getClassMetadata($entityClass)->getTableName();
            $connection = $this->entityManager->getConnection();

            $columnName = $this->entityManager->getClassMetadata($entityClass)->getColumnName($property);
            $sql = sprintf('SELECT `%s` FROM `%s` WHERE `%s` IS NOT NULL', $columnName, $tableName, $columnName);
            $rows = $connection->executeQuery($sql)->fetchAllAssociative();

            $counts = [];
            foreach ($rows as $row) {
                $val = $row[$columnName] ?? null;
                if ($val === null) {
                    continue;
                }

                if (is_string($val)) {
                    $decoded = json_decode($val, true);
                    if (is_array($decoded)) {
                        foreach (array_keys($decoded) as $stateKey) {
                            $counts[(string)$stateKey] = ($counts[(string)$stateKey] ?? 0) + 1;
                        }
                    } else {
                        $counts[$val] = ($counts[$val] ?? 0) + 1;
                    }
                } elseif (is_array($val)) {
                    foreach (array_keys($val) as $stateKey) {
                        $counts[(string)$stateKey] = ($counts[(string)$stateKey] ?? 0) + 1;
                    }
                }
            }

            return $counts;
        } catch (\Throwable) {
            return [];
        }
    }

    private function translateKey(string $key, string $domain = 'process'): string
    {
        $translated = $this->translator->trans($key, [], $domain);
        if ($translated === $key && str_contains($key, '.')) {
            $translated = $this->translator->trans($key, [], 'messages');
        }

        if ($translated === $key) {
            $clean = str_replace(['validation.', 'btn.', '_'], ['', '', ' '], $key);
            return ucfirst($clean);
        }

        return $translated;
    }

    private function deducePlaceColor(string $placeName): string
    {
        if (str_contains($placeName, 'init') || str_contains($placeName, 'brouillon')) {
            return 'info';
        }
        if (str_contains($placeName, 'redaction') || str_contains($placeName, 'saisie')) {
            return 'warning';
        }
        if (str_contains($placeName, 'reserve')) {
            return 'warning';
        }
        if (str_contains($placeName, 'non_ouverture') || str_contains($placeName, 'refus')) {
            return 'danger';
        }
        if (str_contains($placeName, 'publie') || str_contains($placeName, 'valide')) {
            return 'success';
        }
        if (str_contains($placeName, 'cfvu') || str_contains($placeName, 'conseil') || str_contains($placeName, 'central')) {
            return 'primary';
        }

        return 'info';
    }

    private function deducePlaceIcon(string $placeName): string
    {
        if (str_contains($placeName, 'redaction') || str_contains($placeName, 'saisie')) {
            return 'ph:pencil-simple-line';
        }
        if (str_contains($placeName, 'conseil')) {
            return 'ph:users-three';
        }
        if (str_contains($placeName, 'cfvu')) {
            return 'ph:shield-check';
        }
        if (str_contains($placeName, 'central') || str_contains($placeName, 'ses')) {
            return 'ph:buildings';
        }
        if (str_contains($placeName, 'publie')) {
            return 'ph:megaphone';
        }
        if (str_contains($placeName, 'reserve')) {
            return 'ph:warning-circle';
        }
        if (str_contains($placeName, 'refus') || str_contains($placeName, 'non_ouverture')) {
            return 'ph:x-circle';
        }

        return 'ph:circle';
    }

    private function deduceTransitionType(string $transitionName): string
    {
        if (str_contains($transitionName, 'reserve')) {
            return 'reserver';
        }
        if (str_contains($transitionName, 'reouvrir') || str_contains($transitionName, 'retour')) {
            return 'reouvrir';
        }
        if (str_contains($transitionName, 'refuser') || str_contains($transitionName, 'non_ouverture')) {
            return 'refuser';
        }
        if (str_contains($transitionName, 'valider') || str_contains($transitionName, 'soumettre') || str_contains($transitionName, 'publier')) {
            return 'valider';
        }

        return 'action';
    }

    private function deduceTransitionIcon(string $type): string
    {
        return match ($type) {
            'valider' => 'ph:check-circle',
            'reserver' => 'ph:warning-circle',
            'refuser' => 'ph:x-circle',
            'reouvrir' => 'ph:arrow-counter-clockwise',
            default => 'ph:arrow-right',
        };
    }

    private function resolveButtonClass(string $type, array $metadata): string
    {
        if (isset($metadata['btn'])) {
            return 'btn-' . $metadata['btn'];
        }

        return match ($type) {
            'valider' => 'btn-success',
            'reserver' => 'btn-warning',
            'refuser' => 'btn-danger',
            'reouvrir' => 'btn-secondary',
            default => 'btn-primary',
        };
    }

    private function resolveTypeBadgeClass(string $type): string
    {
        return match ($type) {
            'valider' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700',
            'reserver' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border-amber-300 dark:border-amber-700',
            'refuser' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border-rose-300 dark:border-rose-700',
            'reouvrir' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300 border-sky-300 dark:border-sky-700',
            default => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700',
        };
    }

    private function resolveTailwindBg(string $color): string
    {
        return match ($color) {
            'success' => 'bg-emerald-50 dark:bg-emerald-950/40',
            'warning' => 'bg-amber-50 dark:bg-amber-950/40',
            'danger' => 'bg-rose-50 dark:bg-rose-950/40',
            'primary' => 'bg-indigo-50 dark:bg-indigo-950/40',
            default => 'bg-sky-50 dark:bg-sky-950/40',
        };
    }

    private function resolveTailwindBorder(string $color): string
    {
        return match ($color) {
            'success' => 'border-emerald-500 dark:border-emerald-500',
            'warning' => 'border-amber-500 dark:border-amber-500',
            'danger' => 'border-rose-500 dark:border-rose-500',
            'primary' => 'border-indigo-500 dark:border-indigo-500',
            default => 'border-sky-500 dark:border-sky-500',
        };
    }

    private function resolveTailwindText(string $color): string
    {
        return match ($color) {
            'success' => 'text-emerald-700 dark:text-emerald-400',
            'warning' => 'text-amber-700 dark:text-amber-400',
            'danger' => 'text-rose-700 dark:text-rose-400',
            'primary' => 'text-indigo-700 dark:text-indigo-400',
            default => 'text-sky-700 dark:text-sky-400',
        };
    }

    private function getHexColor(string $color): string
    {
        return match ($color) {
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'primary' => '#6366f1',
            default => '#0ea5e9',
        };
    }

    private function getHexBorderColor(string $color): string
    {
        return match ($color) {
            'success' => '#059669',
            'warning' => '#d97706',
            'danger' => '#dc2626',
            'primary' => '#4f46e5',
            default => '#0284c7',
        };
    }

    private function getEdgeColor(string $type): string
    {
        return match ($type) {
            'valider' => '#10b981',
            'reserver' => '#f59e0b',
            'refuser' => '#ef4444',
            'reouvrir' => '#0ea5e9',
            default => '#94a3b8',
        };
    }

    private function getEdgeStyle(string $type): string
    {
        return match ($type) {
            'reouvrir' => 'dashed',
            'reserver' => 'dashed',
            default => 'solid',
        };
    }
}
