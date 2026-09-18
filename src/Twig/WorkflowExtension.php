<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/WorkflowExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 19/07/2023 11:11
 */

namespace App\Twig;

use App\Classes\GetDpeParcours;
use App\Entity\ChangeRf;
use App\Entity\DpeParcours;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\TypeModificationDpeEnum;
use App\Utils\Access;
use App\Workflow\Service\ValidationService;
use App\Workflow\Service\WorkflowMetadataService;
use App\Workflow\Validator\ValidationResult;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class WorkflowExtension extends AbstractExtension
{
    public function __construct(
        #[Target('fiche')]
        private readonly WorkflowInterface $ficheWorkflow,
        #[Target('dpeParcours')]
        private readonly WorkflowInterface $dpeParcoursWorkflow,
        #[Target('changeRf')]
        private readonly WorkflowInterface $changeRfWorkflow,
        private readonly TranslatorInterface     $translatable,
        private readonly WorkflowMetadataService $metadataService,
        private readonly ValidationService       $validationService,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('afficheProcess', $this->afficheProcess(...)),
        ];
    }


    public function getFunctions(): array
    {
        return [
            new TwigFunction('isPass', $this->isPass(...)),
            new TwigFunction('isRefuse', $this->isRefuse(...)),
            new TwigFunction('isPublie', $this->isPublie(...)),
            new TwigFunction('isOuvrable', $this->isOuvrable(...)),
            new TwigFunction('isOuvert', $this->isOuvert(...)),
            new TwigFunction('isPlace', $this->isPlace(...)),
            new TwigFunction('isAccessible', $this->isAccessible(...)),
            new TwigFunction('hasHistorique', $this->hasHistorique(...)),
            new TwigFunction('hasTransitions', $this->hasTransitions(...)),
            // Nouvelles fonctions pour les métadonnées et la validation
//            new TwigFunction('workflow_place_meta', $this->getPlaceMeta(...)),
//            new TwigFunction('workflow_transition_meta', $this->getTransitionMeta(...)),
//            new TwigFunction('workflow_place_icon', $this->getPlaceIcon(...)),
//            new TwigFunction('workflow_place_color', $this->getPlaceColor(...)),
//            new TwigFunction('workflow_place_label', $this->getPlaceLabel(...)),
//            new TwigFunction('workflow_place_description', $this->getPlaceDescription(...)),
//            new TwigFunction('workflow_place_help', $this->getPlaceHelpText(...)),
////            new TwigFunction('workflow_button_config', $this->getButtonConfig(...)),
////            new TwigFunction('workflow_stepper_places', $this->getStepperPlaces(...)),
////            new TwigFunction('workflow_places_by_category', $this->getPlacesByCategory(...)),
//            new TwigFunction('validate_step', $this->validateStep(...)),
//            new TwigFunction('can_validate_transition', $this->canValidateTransition(...)),
        ];
    }

    public function afficheProcess(string $process, bool $withWorkflow = true)
    {
        $parts = explode('_', $process, 2);
        if (count($parts) !== 2) {
            return $process; // Retourne la chaîne d'origine si le format n'est pas correct
        }

        $workflowKey = $parts[0];
        $transition = $parts[1];

        try {
            $workflow = $this->getWorkflow($workflowKey);
            $definition = $workflow->getDefinition();
            $transitionObject = null;
            foreach ($definition->getPlaces() as $t) {
                if ($t === $transition) {
                    $transitionObject = $t;
                    break;
                }
            }

            if ($transitionObject !== null) {
                $meta = $workflow->getMetadataStore()->getPlaceMetadata($transitionObject);
                if (is_array($meta) && array_key_exists('label', $meta) && $meta['label']) {
                    if ($withWorkflow) {
                        return $this->translatable->trans($meta['label'], [], 'process') . ' (' . $workflowKey . ')';
                    }
                    return $this->translatable->trans($meta['label'], [], 'process');
                }
            }
        } catch (\Throwable $e) {
            // fallback au nom de la transition en cas d'erreur
        }

        return $this->translatable->trans($transition, [], 'process') . ' (' . $workflowKey . ')';
    }

    public function isAccessible(DpeParcours|Formation $dpeParcours, string $state = 'cfvu'): bool
    {
        if ($dpeParcours instanceof Formation) {
            return Access::isAccessibleMention($dpeParcours, $state);
        }
        return Access::isAccessible($dpeParcours, $state);

    }

    public function hasHistorique(
        Parcours|FicheMatiere|Formation|ChangeRf $entity,
        string                $key,
        array                 $historique
    ): string {
        if (array_key_exists($key, $historique)) {
            return match ($historique[$key]->getEtat()) {
                'valide' => 'btn-success',
                'force_admin' => 'btn-warning',
                'reserve', 'laisserPasser' => 'btn-warning',
                'refuse' => 'btn-danger',
                default => 'btn-muted',
            };
        }

        return 'btn-muted';
    }

    public function isPlace(string $workflow, Parcours|FicheMatiere|Formation|ChangeRf $entity, string $place): bool
    {
        //si le workflow est change_rf, supprimer de $place le début changeRf.
        //        if ($workflow === 'changeRf') {
        //            $place = str_replace('changeRf.', '', $place);
        //        }


        $actualPlaces = $this->getPlacesFromEntity($entity, $workflow);
        if ($actualPlaces === false) {
            return false;
        }

        if (array_key_exists($place, $actualPlaces)) {
            return true;
        }

        return false;
    }

    public function isPass(string $workflowTexte, Parcours|FicheMatiere|Formation $entity, string $place): bool
    {
        $actualPlaces = $this->getPlacesFromEntity($entity, $workflowTexte);

        if ($actualPlaces === false) {
            return false;
        }

        $workflow = $this->getWorkflow('parcours');

        $definition = $workflow->getDefinition();
        $places = array_keys($definition->getPlaces());

        $indexActualPlace = array_search(array_keys($actualPlaces)[0], $places);
        $indexPlace = array_search($place, $places);
        return $indexActualPlace > $indexPlace;
    }

    public function isRefuse(string $workflow, Parcours|FicheMatiere|Formation $entity): bool
    {
        $actualPlaces = $this->getPlacesFromEntity($entity, $workflow);
        if ($actualPlaces === false) {
            return false;
        }

        if (count($actualPlaces) > 0) {
            return str_starts_with(array_keys($actualPlaces)[0], 'refuse');
        }

        return false;
    }

    public function isPublie(Parcours|Formation|FicheMatiere $entity, string $type = 'parcours'): bool
    {
        if ($type === 'fiche') {
            $places = $this->getWorkflow('fiche')->getMarking($entity)->getPlaces();
            if (count($places) > 0) {
                return str_starts_with(array_keys($places)[0], 'publie');
            }

            return false;
        }

        if ($type === 'formation') {
            $dpeParcours = GetDpeParcours::getFromFormation($entity); //todo: comment gérer depuis Formation?
        } elseif ($type === 'parcours') {
            $dpeParcours = GetDpeParcours::getFromParcours($entity);
        } else {
            $dpeParcours = null;
        }
        //passer par le DpeWorkflow

        if (null === $dpeParcours) {
            return false;
        }

        $places = $this->getWorkflow('dpe')->getMarking($dpeParcours)->getPlaces();
        if (count($places) > 0) {
            return str_starts_with(array_keys($places)[0], 'publie');
        }

        return false;
    }

    public function isOuvrable(Parcours|Formation $entity, string $type = 'parcours'): bool
    {
        if ($type === 'formation') {
            $dpeParcours = GetDpeParcours::getFromFormation($entity); //todo: comment gérer depuis Formation?
        } elseif ($type === 'parcours') {
            $dpeParcours = GetDpeParcours::getFromParcours($entity);
        }
        //passer par le DpeWorkflow

        if (null === $dpeParcours) {
            return false;
        }
        $places = $this->getWorkflow('dpe')->getMarking($dpeParcours)->getPlaces();

        if (count($places) > 0) {
            return str_starts_with(array_keys($places)[0], 'publie') ||
                str_starts_with(array_keys($places)[0], 'soumis_parcours') ||
                str_starts_with(array_keys($places)[0], 'soumis_dpe_composante') ||
                str_starts_with(array_keys($places)[0], 'soumis_central') ||
                str_starts_with(array_keys($places)[0], 'soumis_conseil') ||
                str_starts_with(array_keys($places)[0], 'valide_a_publier') ||
                str_starts_with(array_keys($places)[0], 'tacite_reconduction') ||
                str_starts_with(array_keys($places)[0], 'valide_cfvu');//todo: soumis_central que si pas SES ou Admin. SES peut encore gérer sur cette étable
        }

        return false;
    }

    public function isOuvert(Parcours|Formation|DpeParcours $entity): bool
    {
        return Access::isOuvert($entity);
    }

    private function getWorkflow(string $workflow): WorkflowInterface
    {
        return match ($workflow) {
            'dpe', 'parcours' => $this->dpeParcoursWorkflow,
            'fiche' => $this->ficheWorkflow,
            'changeRf' => $this->changeRfWorkflow,
        };
    }

    private function getPlacesFromEntity(Formation|Parcours|FicheMatiere|ChangeRf $entity, string $workflow): array|false
    {
        if ($entity instanceof Parcours) {
            $dpeParcours = GetDpeParcours::getFromParcours($entity);
            if (null === $dpeParcours) {
                return false;
            }
            $actualPlaces = $this->getWorkflow('parcours')->getMarking($dpeParcours)->getPlaces();
        } elseif ($workflow === 'dpe' && $entity instanceof Formation) {
            $dpeParcours = GetDpeParcours::getFromFormation($entity);
            if (null === $dpeParcours) {
                return false;
            }
            $actualPlaces = $this->getWorkflow('parcours')->getMarking($dpeParcours)->getPlaces();
        } else {
            $actualPlaces = $this->getWorkflow($workflow)->getMarking($entity)->getPlaces();
        }

        return $actualPlaces;
    }

    public function hasTransitions(DpeParcours|ChangeRf|FicheMatiere $entity, string $worflow = 'parcours'): array
    {
        $data['valider'] = [];
        $data['reserver'] = [];
        $data['refuser'] = [];

        $transitions = $this->getWorkflow($worflow)->getEnabledTransitions($entity);

        foreach ($transitions as $transition) {
            $meta = $this->getWorkflow($worflow)->getMetadataStore()->getTransitionMetadata($transition);
            if (array_key_exists('type', $meta)) {
                if (array_key_exists('display', $meta) && $meta['display'] === false) {
                    continue;
                }
                $data[$meta['type']][$transition->getName()] = [
                    'transition' => $transition,
                    'meta' => $meta,
                ];
            }
        }
        return $data;
    }

    // ========================================
    // Nouvelles méthodes pour les métadonnées
    // ========================================

    /**
     * Récupère toutes les métadonnées d'une place.
     */
    public function getPlaceMeta(string $placeName, string $workflowName = 'dpeParcours'): array
    {
        return $this->metadataService->getPlaceMetadata($placeName, $workflowName);
    }

    /**
     * Récupère toutes les métadonnées d'une transition.
     */
    public function getTransitionMeta(string $transitionName, string $workflowName = 'dpeParcours'): array
    {
        return $this->metadataService->getTransitionMetadata($transitionName, $workflowName);
    }

    /**
     * Récupère l'icône d'une place.
     */
    public function getPlaceIcon(string $placeName, string $workflowName = 'dpeParcours'): ?string
    {
        return $this->metadataService->getPlaceIcon($placeName, $workflowName);
    }

    /**
     * Récupère la couleur d'une place.
     */
    public function getPlaceColor(string $placeName, string $workflowName = 'dpeParcours'): string
    {
        return $this->metadataService->getPlaceColor($placeName, $workflowName);
    }

    /**
     * Récupère le label traduit d'une place.
     */
    public function getPlaceLabel(string $placeName, string $workflowName = 'dpeParcours'): string
    {
        $label = $this->metadataService->getPlaceLabel($placeName, $workflowName);

        if ($label !== null) {
            return $this->translatable->trans($label, [], 'process');
        }

        return $placeName;
    }

    /**
     * Récupère la description d'une place.
     */
    public function getPlaceDescription(string $placeName, string $workflowName = 'dpeParcours'): ?string
    {
        return $this->metadataService->getPlaceDescription($placeName, $workflowName);
    }

    /**
     * Récupère le texte d'aide d'une place.
     */
    public function getPlaceHelpText(string $placeName, string $workflowName = 'dpeParcours'): ?string
    {
        return $this->metadataService->getPlaceHelpText($placeName, $workflowName);
    }

    /**
     * Récupère la configuration du bouton pour une transition.
     *
     * @return array{label: string, class: string, icon: ?string, confirmation: array, comment: array}
     */
    public function getButtonConfig(string $transitionName, string $workflowName = 'dpeParcours'): array
    {
        $config = $this->metadataService->getTransitionButtonConfig($transitionName, $workflowName);

        // Traduire le label si présent
        if (isset($config['label'])) {
            $config['label'] = $this->translatable->trans($config['label'], [], 'process');
        }

        // Traduire le message de confirmation si présent
        if (isset($config['confirmation']['message'])) {
            $config['confirmation']['message'] = $this->translatable->trans(
                $config['confirmation']['message'],
                [],
                'process'
            );
        }

        // Traduire le placeholder du commentaire si présent
        if (isset($config['comment']['placeholder'])) {
            $config['comment']['placeholder'] = $this->translatable->trans(
                $config['comment']['placeholder'],
                [],
                'process'
            );
        }

        return $config;
    }

    /**
     * Récupère les places à afficher dans le stepper.
     *
     * @return array<string, array>
     */
    public function getStepperPlaces(string $workflowName = 'dpeParcours'): array
    {
        $places = $this->metadataService->getStepperPlaces($workflowName);

        // Traduire les labels
        foreach ($places as $placeName => &$metadata) {
            if (isset($metadata['label'])) {
                $metadata['label_translated'] = $this->translatable->trans($metadata['label'], [], 'process');
            }
        }

        return $places;
    }

    /**
     * Récupère les places d'une catégorie donnée.
     *
     * @return array<string, array>
     */
    public function getPlacesByCategory(string $category, string $workflowName = 'dpeParcours'): array
    {
        return $this->metadataService->getPlacesByCategory($category, $workflowName);
    }

    // ========================================
    // Méthodes de validation
    // ========================================

    /**
     * Valide un DpeParcours pour une étape donnée.
     *
     * @return ValidationResult
     */
    public function validateStep(DpeParcours $dpeParcours, string $stepCode): ValidationResult
    {
        return $this->validationService->validateForStep($dpeParcours, $stepCode);
    }

    /**
     * Vérifie si une transition vers une étape est autorisée.
     */
    public function canValidateTransition(DpeParcours $dpeParcours, string $targetStepCode): bool
    {
        return $this->validationService->canTransition($dpeParcours, $targetStepCode);
    }
}
