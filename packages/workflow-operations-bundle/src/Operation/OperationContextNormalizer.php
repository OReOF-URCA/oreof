<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Operation;

use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class OperationContextNormalizer
{
    public function __construct(private WorkflowOperationFactory $operationFactory)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $metadata
     */
    public function normalize(
        WorkflowInterface $workflow,
        string $transitionName,
        ?object $actor,
        array $input,
        array $metadata = [],
    ): OperationContext {
        $operation = $this->operationFactory->create($workflow, $transitionName);
        $configuration = $operation->metadata['context'] ?? [];
        $aliases = is_array($configuration) ? ($configuration['aliases'] ?? []) : [];

        if (!is_array($aliases)) {
            throw new \InvalidArgumentException(sprintf(
                'The context aliases of transition "%s" must be an array.',
                $transitionName,
            ));
        }

        foreach ($aliases as $inputKey => $contextKey) {
            if (!is_string($inputKey) || !is_string($contextKey) || '' === trim($contextKey)) {
                throw new \InvalidArgumentException(sprintf(
                    'The context aliases of transition "%s" must map input keys to non-empty context keys.',
                    $transitionName,
                ));
            }
        }

        /** @var array<string, string> $aliases */
        return OperationContext::fromInput($actor, $input, $aliases, $metadata);
    }
}
