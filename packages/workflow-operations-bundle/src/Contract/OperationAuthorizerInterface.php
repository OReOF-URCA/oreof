<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Contract;

use Dannebicque\WorkflowOperationsBundle\Model\AuthorizationDecision;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('workflow_operations.authorizer')]
interface OperationAuthorizerInterface
{
    public function supports(object $subject, WorkflowOperation $operation): bool;

    public function decide(
        object $subject,
        WorkflowOperation $operation,
        OperationContext $context,
    ): AuthorizationDecision;
}
