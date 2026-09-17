<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Exception;

use Dannebicque\WorkflowOperationsBundle\Model\OperationInspection;

final class OperationNotExecutableException extends \RuntimeException
{
    public function __construct(public readonly OperationInspection $inspection)
    {
        parent::__construct(sprintf(
            'Operation "%s" from workflow "%s" is not executable (%s).',
            $inspection->operation->transitionName,
            $inspection->operation->workflowName,
            $inspection->status->value,
        ));
    }
}
