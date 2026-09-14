<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/ValidationProcess.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 19/07/2023 17:01
 */

namespace App\Classes;

use App\Entity\DpeParcours;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Workflow\WorkflowInterface;

class ValidationProcess extends AbstractValidationProcess
{
    private readonly OptionsResolver $placeMetaResolver;
    private readonly OptionsResolver $transitionMetaResolver;

    public function __construct(protected WorkflowInterface $dpeParcoursWorkflow)
    {
        $this->placeMetaResolver = $this->createPlaceMetaResolver();
        $this->transitionMetaResolver = $this->createTransitionMetaResolver();

        $places = $dpeParcoursWorkflow->getDefinition()->getPlaces();
        $data = [];
        $dataAll = [];
        foreach ($places as $place) {
            $meta = $this->normalizePlaceMeta(
                $dpeParcoursWorkflow->getMetadataStore()->getPlaceMetadata($place),
                $place,
                $this->placeMetaResolver
            );
            if (array_key_exists('process', $meta) && (bool)$meta['process'] === true) {
                $data[$place] = $meta;
            }

            $dataAll[$place] = $meta;
        }

        $this->transitionsAll = [];
        $transitions = $this->dpeParcoursWorkflow->getDefinition()->getTransitions();
        foreach ($transitions as $trans) {
            $this->transitionsAll[$trans->getName()] = $trans;
        }

        $this->processAll = $dataAll;
        $this->process = $data;
    }

    public const ETAPES_COMPOSANTE = [
        'en_cours_redaction',
        'soumis_parcours',
        'soumis_dpe_composante',
        'soumis_conseil',
        'en_cours_redaction_ss_cfvu',
    ];

    public function getProcessComposante(): array
    {
        return array_intersect_key($this->processAll, array_flip(self::ETAPES_COMPOSANTE));
    }

    public function getMetaFromTransition(string $transition): array
    {
        $transitions = $this->dpeParcoursWorkflow->getDefinition()->getTransitions();
        foreach ($transitions as $trans) {
            if ($trans->getName() === $transition) {
                return $this->normalizeTransitionMeta(
                    $this->dpeParcoursWorkflow->getMetadataStore()->getTransitionMetadata($trans),
                    $trans->getName(),
                    $this->transitionMetaResolver
                );
            }
        }

        return [];
    }

    public function getOptionsForStep(DpeParcours $dpeParcours): array
    {
        $enabled = [];

        $transitions = $this->dpeParcoursWorkflow->getDefinition()->getTransitions();
        foreach ($transitions as $t) {
            if ($this->dpeParcoursWorkflow->can($dpeParcours, $t->getName())) {
                $enabled[] = $t;
            }
        }

        $options = [];
        foreach ($enabled as $trans) {
            $meta = $this->normalizeTransitionMeta(
                $this->dpeParcoursWorkflow->getMetadataStore()->getTransitionMetadata($trans),
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

    public function getNextStepFromPlace(string $currentPlace): ?array
    {
        $transitions = $this->dpeParcoursWorkflow->getDefinition()->getTransitions();

        // On privilégie le flux "normal" de validation quand il existe.
        foreach ($transitions as $transition) {
            if (!in_array($currentPlace, (array)$transition->getFroms(), true)) {
                continue;
            }

            $meta = $this->dpeParcoursWorkflow->getMetadataStore()->getTransitionMetadata($transition);
            if (($meta['type'] ?? null) !== 'valider') {
                continue;
            }

            $to = (array)$transition->getTos();
            if ($to === []) {
                continue;
            }

            return [
                'transition' => $transition->getName(),
                'to' => $to[0],
            ];
        }

        // Fallback: première transition disponible dans la définition.
        foreach ($transitions as $transition) {
            if (!in_array($currentPlace, (array)$transition->getFroms(), true)) {
                continue;
            }

            $to = (array)$transition->getTos();
            if ($to === []) {
                continue;
            }

            return [
                'transition' => $transition->getName(),
                'to' => $to[0],
            ];
        }

        return null;
    }
}
