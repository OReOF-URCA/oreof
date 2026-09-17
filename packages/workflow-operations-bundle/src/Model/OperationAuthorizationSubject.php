<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Model;

final readonly class OperationAuthorizationSubject
{
    public function __construct(
        public object $subject,
        public WorkflowOperation $operation,
        public OperationContext $context,
    ) {
    }
}
