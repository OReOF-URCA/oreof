<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Model;

enum BlockerSeverity: string
{
    case Information = 'information';
    case Warning = 'warning';
    case Error = 'error';
}
