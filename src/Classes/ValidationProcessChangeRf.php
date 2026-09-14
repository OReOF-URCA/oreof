<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/ValidationProcess.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 19/07/2023 17:01
 */

namespace App\Classes;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Workflow\WorkflowInterface;

class ValidationProcessChangeRf extends AbstractValidationProcess
{
    private readonly OptionsResolver $placeMetaResolver;
    private readonly OptionsResolver $transitionMetaResolver;

    public function __construct(protected WorkflowInterface $changeRfWorkflow)
    {
        $this->placeMetaResolver = $this->createPlaceMetaResolver();
        $this->transitionMetaResolver = $this->createTransitionMetaResolver();

        $places = $changeRfWorkflow->getDefinition()->getPlaces();
        $data = [];
        $dataAll = [];
        foreach ($places as $place) {
            $meta = $this->normalizePlaceMeta(
                $changeRfWorkflow->getMetadataStore()->getPlaceMetadata($place),
                $place,
                $this->placeMetaResolver
            );

            $data[$place] = $meta;
            $dataAll[$place] = $meta;
        }

        $this->transitionsAll = [];
        $transitions = $changeRfWorkflow->getDefinition()->getTransitions();
        foreach ($transitions as $trans) {
            $this->transitionsAll[$trans->getName()] = $trans;
        }

        $this->processAll = $dataAll;
        $this->process = $data;
    }

    public const ETAPES_COMPOSANTE = [
        'demande_initialisee',
        'soumis_conseil',
    ];

    public function getProcessComposante(): array
    {
        return array_intersect_key($this->processAll, array_flip(self::ETAPES_COMPOSANTE));
    }

    public function getMetaFromTransition(string $transition): array
    {
        $transitions = $this->changeRfWorkflow->getDefinition()->getTransitions();
        foreach ($transitions as $trans) {
            if ($trans->getName() === $transition) {
                return $this->normalizeTransitionMeta(
                    $this->changeRfWorkflow->getMetadataStore()->getTransitionMetadata($trans),
                    $trans->getName(),
                    $this->transitionMetaResolver
                );
            }
        }

        return [];
    }
}
