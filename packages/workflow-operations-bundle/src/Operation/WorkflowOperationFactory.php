<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Operation;

use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Symfony\Component\Workflow\WorkflowInterface;

final class WorkflowOperationFactory
{
    public function create(WorkflowInterface $workflow, string $transitionName): WorkflowOperation
    {
        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            if ($transitionName !== $transition->getName()) {
                continue;
            }

            $workflowMetadata = $workflow->getMetadataStore()->getWorkflowMetadata();
            $defaultMetadata = $workflowMetadata['operation_defaults'] ?? [];
            if (!is_array($defaultMetadata)) {
                $defaultMetadata = [];
            }

            return new WorkflowOperation(
                workflowName: $workflow->getName(),
                transitionName: $transition->getName(),
                fromPlaces: $transition->getFroms(),
                toPlaces: $transition->getTos(),
                metadata: array_replace_recursive(
                    $defaultMetadata,
                    $workflow->getMetadataStore()->getTransitionMetadata($transition),
                ),
            );
        }

        throw new \InvalidArgumentException(sprintf(
            'Transition "%s" does not exist in workflow "%s".',
            $transitionName,
            $workflow->getName(),
        ));
    }
}
