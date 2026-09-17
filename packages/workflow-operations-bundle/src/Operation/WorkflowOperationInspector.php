<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Operation;

use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\OperationInspection;
use Dannebicque\WorkflowOperationsBundle\Model\OperationStatus;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class WorkflowOperationInspector
{
    public function __construct(
        private WorkflowOperationFactory $operationFactory,
        private OperationAuthorizationChecker $authorizationChecker,
        private OperationBlockerCollector $blockerCollector,
    ) {
    }

    public function inspect(
        WorkflowInterface $workflow,
        object $subject,
        string $transitionName,
        ?OperationContext $context = null,
    ): OperationInspection {
        $context ??= OperationContext::empty();
        $operation = $this->operationFactory->create($workflow, $transitionName);
        $authorization = $this->authorizationChecker->decide($subject, $operation, $context);

        if (!$authorization->allowed) {
            return new OperationInspection(
                operation: $operation,
                status: OperationStatus::Forbidden,
                authorization: $authorization,
                workflowEnabled: false,
                blockers: new OperationBlockerCollection(),
            );
        }

        $workflowEnabled = $workflow->can($subject, $transitionName, $context->workflowContext());
        if (!$workflowEnabled) {
            return new OperationInspection(
                operation: $operation,
                status: OperationStatus::Unavailable,
                authorization: $authorization,
                workflowEnabled: false,
                blockers: new OperationBlockerCollection(),
            );
        }

        $blockers = $this->blockerCollector->collect($subject, $operation, $context);

        return new OperationInspection(
            operation: $operation,
            status: $blockers->hasBlockingItems() ? OperationStatus::Blocked : OperationStatus::Ready,
            authorization: $authorization,
            workflowEnabled: true,
            blockers: $blockers,
        );
    }
}
