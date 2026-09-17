<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Operation;

use Dannebicque\WorkflowOperationsBundle\Exception\OperationNotExecutableException;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class WorkflowOperationExecutor
{
    public function __construct(
        private WorkflowOperationInspector $inspector,
        private OperationHandlerRegistry $handlerRegistry,
    ) {
    }

    public function execute(
        WorkflowInterface $workflow,
        object $subject,
        string $transitionName,
        ?OperationContext $context = null,
    ): WorkflowOperation {
        $context ??= OperationContext::empty();
        $inspection = $this->inspector->inspect($workflow, $subject, $transitionName, $context);

        if (!$inspection->canExecute()) {
            throw new OperationNotExecutableException($inspection);
        }

        $this->handlerRegistry->find($subject, $inspection->operation)?->handle(
            $subject,
            $inspection->operation,
            $context,
        );

        $workflow->apply($subject, $transitionName, $context->workflowContext());

        return $inspection->operation;
    }
}
