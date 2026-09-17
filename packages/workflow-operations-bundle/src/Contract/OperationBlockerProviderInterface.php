<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Contract;

use Dannebicque\WorkflowOperationsBundle\Model\OperationBlocker;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('workflow_operations.blocker_provider')]
interface OperationBlockerProviderInterface
{
    public function supports(object $subject, WorkflowOperation $operation): bool;

    /** @return iterable<OperationBlocker> */
    public function getBlockers(
        object $subject,
        WorkflowOperation $operation,
        OperationContext $context,
    ): iterable;
}
