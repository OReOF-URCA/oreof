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

abstract class AbstractValidationProcess
{
    // Minimal metadata contract expected by process controllers.
    public const REQUIRED_PLACE_META_KEYS = ['label', 'process', 'color'];
    public const REQUIRED_TRANSITION_META_KEYS = ['type', 'button_class', 'button_icon', 'form'];

    protected array $process = [];
    protected array $processAll = [];
    protected array $transitionsAll = [];

    public function getProcess(): array
    {
        return $this->process;
    }

    public function getProcessForListe(): array
    {
        $tab = [];
        foreach ($this->process as $cle => $etape) {
            $tab['workflow.' . $etape['label']] = $cle;
        }

        return $tab;
    }

    public function getProcessAll(): array
    {
        return $this->processAll;
    }

    public function getEtapeCle(string $etape, string $cle): string
    {
       if (array_key_exists($etape, $this->process)) {
           return $this->process[$etape][$cle];
       }

         return '';
    }

    public function getEtapeFromAll(string $etape): array
    {
        if (array_key_exists($etape, $this->processAll)) {
            return $this->processAll[$etape];
        }

        return [];
    }

    public function getEtape(string $etape): array
    {
        if (array_key_exists($etape, $this->process)) {
            return $this->process[$etape];
        }

        return [];
    }

    public function getTransitionsAll(): array
    {
        return $this->transitionsAll;
    }

    protected function getPlaceMetaKeys(): array
    {
        return ['label', 'process', 'color'];
    }

    protected function getTransitionMetaKeys(): array
    {
        return [
            'label',
            'type',
            'display',
            'hasValidLheo',
            'hasDate',
            'hasUpload',
            'hasUploadNote',
            'hasArgumentaire',
            'button_class',
            'button_icon',
            'handler',
            'description',
            'recipients',
            'view',
            'validation',
            'form',
        ];
    }

    protected function createPlaceMetaResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'label' => null,
            'process' => false,
            'color' => 'info',
        ]);
        $resolver->setAllowedTypes('label', ['null', 'string']);
        $resolver->setAllowedTypes('process', 'bool');
        $resolver->setAllowedTypes('color', 'string');

        return $resolver;
    }

    protected function createTransitionMetaResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'label' => null,
            'type' => null,
            'display' => true,
            'hasValidLheo' => false,
            'hasDate' => false,
            'hasUpload' => false,
            'hasUploadNote' => false,
            'hasArgumentaire' => false,
            'button_class' => null,
            'button_icon' => null,
            'handler' => null,
            'description' => null,
            'recipients' => [],
            'view' => null,
            'validation' => [],
            'form' => [],
        ]);

        $resolver->setAllowedTypes('label', ['null', 'string']);
        $resolver->setAllowedTypes('type', ['null', 'string']);
        $resolver->setAllowedTypes('display', 'bool');
        $resolver->setAllowedTypes('hasValidLheo', 'bool');
        $resolver->setAllowedTypes('hasDate', 'bool');
        $resolver->setAllowedTypes('hasUpload', 'bool');
        $resolver->setAllowedTypes('hasUploadNote', 'bool');
        $resolver->setAllowedTypes('hasArgumentaire', 'bool');
        $resolver->setAllowedTypes('button_class', ['null', 'string']);
        $resolver->setAllowedTypes('button_icon', ['null', 'string']);
        $resolver->setAllowedTypes('handler', ['null', 'string']);
        $resolver->setAllowedTypes('description', ['null', 'string']);
        $resolver->setAllowedTypes('recipients', 'array');
        $resolver->setAllowedTypes('view', ['null', 'string']);
        $resolver->setAllowedTypes('validation', 'array');
        $resolver->setAllowedTypes('form', 'array');

        return $resolver;
    }

    protected function normalizePlaceMeta(array $meta, string $place, OptionsResolver $resolver): array
    {
        $resolved = $this->resolveKnownKeys($resolver, $meta, $this->getPlaceMetaKeys());
        if (!isset($resolved['label']) || $resolved['label'] === '') {
            $resolved['label'] = $place;
        }

        return $resolved;
    }

    protected function normalizeTransitionMeta(array $meta, string $transitionName, OptionsResolver $resolver): array
    {
        $resolved = $this->resolveKnownKeys($resolver, $meta, $this->getTransitionMetaKeys());
        if (!isset($resolved['label']) || $resolved['label'] === '') {
            $resolved['label'] = $transitionName;
        }

        return $resolved;
    }

    protected function resolveKnownKeys(OptionsResolver $resolver, array $meta, array $keys): array
    {
        $knownMeta = array_intersect_key($meta, array_flip($keys));
        $normalized = $resolver->resolve($knownMeta);

        return array_replace($meta, $normalized);
    }
}
