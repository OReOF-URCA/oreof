<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Operation;

use Dannebicque\WorkflowOperationsBundle\Model\OperationFormDefinition;
use Symfony\Component\Form\AbstractType;

final class OperationFormResolver
{
    /** @param array<string, mixed> $metadata */
    public function resolve(array $metadata): ?OperationFormDefinition
    {
        $configuration = $metadata['form'] ?? null;
        if (null === $configuration) {
            return null;
        }

        if (!is_array($configuration)) {
            throw new \InvalidArgumentException('Operation form metadata must be an array.');
        }

        $type = $configuration['type'] ?? null;
        if (!is_string($type) || !is_subclass_of($type, AbstractType::class)) {
            throw new \InvalidArgumentException('Operation form metadata must contain a valid Symfony form type.');
        }

        $options = $configuration['options'] ?? [];
        if (!is_array($options)) {
            throw new \InvalidArgumentException('Operation form options must be an array.');
        }

        $template = $configuration['template'] ?? null;
        if (null !== $template && !is_string($template)) {
            throw new \InvalidArgumentException('Operation form template must be a string or null.');
        }

        return new OperationFormDefinition($type, $options, $template);
    }

    /** @param array<string, mixed> $metadata */
    public function resolveRequired(array $metadata): OperationFormDefinition
    {
        return $this->resolve($metadata)
            ?? throw new \LogicException('This workflow operation requires form metadata.');
    }
}
