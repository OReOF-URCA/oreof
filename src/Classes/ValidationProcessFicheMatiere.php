<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/ValidationProcess.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 19/07/2023 17:01
 */

namespace App\Classes;

use App\Entity\FicheMatiere;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Workflow\WorkflowInterface;

class ValidationProcessFicheMatiere extends AbstractValidationProcess
{
    private readonly OptionsResolver $placeMetaResolver;
    private readonly OptionsResolver $transitionMetaResolver;

    public function __construct(
        #[Target('fiche')]
        protected WorkflowInterface $ficheWorkflow
    )
    {
        $this->placeMetaResolver = $this->createPlaceMetaResolver();
        $this->transitionMetaResolver = $this->createTransitionMetaResolver();

        $places = $ficheWorkflow->getDefinition()->getPlaces();
        $data = [];
        $dataAll = [];
        foreach ($places as $place) {
            $meta = $this->normalizePlaceMeta(
                $ficheWorkflow->getMetadataStore()->getPlaceMetadata($place),
                $place,
                $this->placeMetaResolver
            );

            if (array_key_exists('process', $meta) && (bool)$meta['process'] === true) {
                $data[$place] = $meta;
            }

            $dataAll[$place] = $meta;
        }

        $this->transitionsAll = [];
        $transitions = $ficheWorkflow->getDefinition()->getTransitions();
        foreach ($transitions as $trans) {
            $this->transitionsAll[$trans->getName()] = $trans;
        }

        $this->processAll = $dataAll;
        $this->process = $data;
    }

    public const ETAPES_COMPOSANTE = [
        'en_cours_redaction',
    ];

    public function getProcessComposante(): array
    {
        return array_intersect_key($this->processAll, array_flip(self::ETAPES_COMPOSANTE));
    }

    public function getMetaFromTransition(string $transition): array
    {
        $transitions = $this->ficheWorkflow->getDefinition()->getTransitions();
        foreach ($transitions as $trans) {
            if ($trans->getName() === $transition) {
                return $this->normalizeTransitionMeta(
                    $this->ficheWorkflow->getMetadataStore()->getTransitionMetadata($trans),
                    $trans->getName(),
                    $this->transitionMetaResolver
                );
            }
        }

        return [];
    }

    public function getOptionsForStep(FicheMatiere $ficheMatiere): array
    {
        $enabled = [];

        $transitions = $this->ficheWorkflow->getDefinition()->getTransitions();
        foreach ($transitions as $t) {
            if ($this->ficheWorkflow->can($ficheMatiere, $t->getName())) {
                $enabled[] = $t;
            }
        }

        $options = [];
        foreach ($enabled as $trans) {
            $meta = $this->normalizeTransitionMeta(
                $this->ficheWorkflow->getMetadataStore()->getTransitionMetadata($trans),
                $trans->getName(),
                $this->transitionMetaResolver
            );
            $name = $trans->getName();
            $options[$name] = [
                'label' => $meta['label'],
                'metadata' => $meta,
            ];
        }

        return $options;
    }
}
