<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Model;

use Dannebicque\WorkflowOperationsBundle\Operation\OperationBlockerCollection;

final readonly class OperationInspection
{
    public function __construct(
        public WorkflowOperation $operation,
        public OperationStatus $status,
        public AuthorizationDecision $authorization,
        public bool $workflowEnabled,
        public OperationBlockerCollection $blockers,
    ) {
    }

    public function canExecute(): bool
    {
        return OperationStatus::Ready === $this->status;
    }
}
