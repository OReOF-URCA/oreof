<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Model;

enum OperationStatus: string
{
    case Forbidden = 'forbidden';
    case Unavailable = 'unavailable';
    case Blocked = 'blocked';
    case Ready = 'ready';
}
