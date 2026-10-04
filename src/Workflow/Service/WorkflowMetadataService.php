<?php

declare(strict_types=1);

namespace App\Workflow\Service;

use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Transition;

/**
 * Service pour accéder aux métadonnées du workflow depuis le YAML.
 *
 * Fournit une API simple pour récupérer les métadonnées des places
 * et transitions définies dans le fichier workflow.yaml.
 */
final class WorkflowMetadataService
{
    public function __construct(
        private readonly Registry $workflowRegistry
    )
    {
    }

    /**
     * Récupère toutes les transitions du workflow.
     *
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return array<string, array<string, mixed>> Map transitionName => metadata
     */
    public function getAllTransitions(string $workflowName = 'dpeParcours'): array
    {
        $workflow = $this->getWorkflow($workflowName);
        $metadataStore = $workflow->getMetadataStore();
        $definition = $workflow->getDefinition();

        $transitions = [];
        foreach ($definition->getTransitions() as $transition) {
            $transitions[$transition->getName()] = array_merge(
                $metadataStore->getTransitionMetadata($transition),
                [
                    'from' => $transition->getFroms(),
                    'to' => $transition->getTos(),
                ]
            );
        }

        return $transitions;
    }

    /**
     * Récupère le workflow par son nom.
     */
    private function getWorkflow(string $workflowName): WorkflowInterface
    {
        return $this->workflowRegistry->get(new \App\Entity\DpeParcours(), $workflowName);
    }

    /**
     * Récupère les métadonnées d'une transition.
     *
     * @param string $transitionName Nom de la transition
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return array<string, mixed> Métadonnées de la transition
     */
    public function getTransitionMetadata(string $transitionName, string $workflowName = 'dpeParcours'): array
    {
        $workflow = $this->getWorkflow($workflowName);
        $metadataStore = $workflow->getMetadataStore();
        $definition = $workflow->getDefinition();

        foreach ($definition->getTransitions() as $transition) {
            if ($transition->getName() === $transitionName) {
                return $metadataStore->getTransitionMetadata($transition);
            }
        }

        return [];
    }

    /**
     * Récupère uniquement les places à afficher dans le stepper.
     *
     * Filtre les places ayant 'process' => true ou 'show_in_stepper' => true.
     *
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return array<string, array<string, mixed>> Places du stepper
     */
    public function getStepperPlaces(string $workflowName = 'dpeParcours'): array
    {
        $allPlaces = $this->getAllPlaces($workflowName);

        return array_filter($allPlaces, function (array $metadata) {
            return ($metadata['process'] ?? false) === true
                || ($metadata['show_in_stepper'] ?? false) === true;
        });
    }

    /**
     * Récupère toutes les places du workflow.
     *
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return array<string, array<string, mixed>> Map placeName => metadata
     */
    public function getAllPlaces(string $workflowName = 'dpeParcours'): array
    {
        $workflow = $this->getWorkflow($workflowName);
        $metadataStore = $workflow->getMetadataStore();
        $definition = $workflow->getDefinition();

        $places = [];
        foreach (array_keys($definition->getPlaces()) as $placeName) {
            $places[$placeName] = $metadataStore->getPlaceMetadata($placeName);
        }

        return $places;
    }

    /**
     * Récupère les métadonnées d'une place.
     *
     * @param string $placeName Nom de la place
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return array<string, mixed> Métadonnées de la place
     */
    public function getPlaceMetadata(string $placeName, string $workflowName = 'dpeParcours'): array
    {
        $workflow = $this->getWorkflow($workflowName);
        $metadataStore = $workflow->getMetadataStore();

        return $metadataStore->getPlaceMetadata($placeName);
    }

    /**
     * Récupère les places par catégorie.
     *
     * @param string $category Catégorie recherchée
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return array<string, array<string, mixed>> Places de la catégorie
     */
    public function getPlacesByCategory(string $category, string $workflowName = 'dpeParcours'): array
    {
        $allPlaces = $this->getAllPlaces($workflowName);

        return array_filter($allPlaces, function (array $metadata) use ($category) {
            return ($metadata['category'] ?? '') === $category;
        });
    }

    /**
     * Récupère la configuration du bouton pour une transition.
     *
     * @param string $transitionName Nom de la transition
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return array{label: string, class: string, icon: ?string, confirmation: array{required: bool, message: ?string}, comment: array{required: bool, placeholder: ?string}}
     */
    public function getTransitionButtonConfig(string $transitionName, string $workflowName = 'dpeParcours'): array
    {
        $metadata = $this->getTransitionMetadata($transitionName, $workflowName);

        return [
            'label' => $metadata['label'] ?? $transitionName,
            'class' => $metadata['button_class'] ?? $this->resolveButtonClass($metadata),
            'icon' => $metadata['button_icon'] ?? ($metadata['icon'] ?? null),
            'confirmation' => [
                'required' => $metadata['confirmation_required'] ?? false,
                'message' => $metadata['confirmation_message'] ?? null,
            ],
            'comment' => [
                'required' => $metadata['requires_comment'] ?? false,
                'placeholder' => $metadata['comment_placeholder'] ?? null,
            ],
        ];
    }

    /**
     * Résout la classe CSS du bouton selon le type de transition.
     */
    private function resolveButtonClass(array $metadata): string
    {
        $type = $metadata['type'] ?? 'default';
        $btn = $metadata['btn'] ?? null;

        if ($btn !== null) {
            return 'btn-' . $btn;
        }

        return match ($type) {
            'valider' => 'btn-success',
            'reserver' => 'btn-warning',
            'refuser' => 'btn-danger',
            default => 'btn-primary',
        };
    }

    /**
     * Récupère les exigences pour une transition (upload, date, commentaire).
     *
     * @param string $transitionName Nom de la transition
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return array{hasUpload: bool, hasDate: bool, requiresComment: bool, hasValidLheo: bool}
     */
    public function getTransitionRequirements(string $transitionName, string $workflowName = 'dpeParcours'): array
    {
        $metadata = $this->getTransitionMetadata($transitionName, $workflowName);

        return [
            'hasUpload' => $metadata['hasUpload'] ?? false,
            'hasDate' => $metadata['hasDate'] ?? false,
            'requiresComment' => $metadata['requires_comment'] ?? false,
            'hasValidLheo' => $metadata['hasValidLheo'] ?? false,
        ];
    }

    /**
     * Récupère l'icône d'une place.
     *
     * @param string $placeName Nom de la place
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return string|null Classe de l'icône (ex: 'fa-check')
     */
    public function getPlaceIcon(string $placeName, string $workflowName = 'dpeParcours'): ?string
    {
        $metadata = $this->getPlaceMetadata($placeName, $workflowName);
        return $metadata['icon'] ?? null;
    }

    /**
     * Récupère la couleur d'une place.
     *
     * @param string $placeName Nom de la place
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return string Couleur (défaut: 'info')
     */
    public function getPlaceColor(string $placeName, string $workflowName = 'dpeParcours'): string
    {
        $metadata = $this->getPlaceMetadata($placeName, $workflowName);
        return $metadata['color'] ?? 'info';
    }

    /**
     * Récupère le label traduit d'une place.
     *
     * @param string $placeName Nom de la place
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return string|null Clé de traduction du label
     */
    public function getPlaceLabel(string $placeName, string $workflowName = 'dpeParcours'): ?string
    {
        $metadata = $this->getPlaceMetadata($placeName, $workflowName);
        return $metadata['label'] ?? null;
    }

    /**
     * Récupère la description d'une place.
     *
     * @param string $placeName Nom de la place
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return string|null Description de la place
     */
    public function getPlaceDescription(string $placeName, string $workflowName = 'dpeParcours'): ?string
    {
        $metadata = $this->getPlaceMetadata($placeName, $workflowName);
        return $metadata['description'] ?? null;
    }

    /**
     * Récupère le texte d'aide d'une place.
     *
     * @param string $placeName Nom de la place
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return string|null Texte d'aide
     */
    public function getPlaceHelpText(string $placeName, string $workflowName = 'dpeParcours'): ?string
    {
        $metadata = $this->getPlaceMetadata($placeName, $workflowName);
        return $metadata['help_text'] ?? null;
    }

    /**
     * Récupère les destinataires des notifications pour une transition.
     *
     * @param string $transitionName Nom de la transition
     * @param string $workflowName Nom du workflow (défaut: dpeParcours)
     * @return array<string> Liste des rôles destinataires
     */
    public function getTransitionRecipients(string $transitionName, string $workflowName = 'dpeParcours'): array
    {
        $metadata = $this->getTransitionMetadata($transitionName, $workflowName);
        return $metadata['recipients'] ?? [];
    }

    /**
     * Vérifie si une place est un état de refus.
     *
     * @param string $placeName Nom de la place
     * @return bool True si c'est un état de refus
     */
    public function isRefuseState(string $placeName): bool
    {
        return str_starts_with($placeName, 'refuse');
    }

    /**
     * Vérifie si une place est un état de validation.
     *
     * @param string $placeName Nom de la place
     * @return bool True si c'est un état de validation en cours
     */
    public function isValidationState(string $placeName): bool
    {
        return str_starts_with($placeName, 'soumis_') || str_starts_with($placeName, 'valide_');
    }

    /**
     * Vérifie si une place est l'état publié.
     *
     * @param string $placeName Nom de la place
     * @return bool True si publié
     */
    public function isPublishedState(string $placeName): bool
    {
        return $placeName === 'publie';
    }
}
