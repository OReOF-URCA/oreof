<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Operation;

use Dannebicque\WorkflowOperationsBundle\Contract\OperationHandlerInterface;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;

final readonly class OperationHandlerRegistry
{
    /** @param iterable<OperationHandlerInterface> $handlers */
    public function __construct(private iterable $handlers)
    {
    }

    public function find(object $subject, WorkflowOperation $operation): ?OperationHandlerInterface
    {
        $matchingHandler = null;

        foreach ($this->handlers as $handler) {
            if (!$handler->supports($subject, $operation)) {
                continue;
            }

            if (null !== $matchingHandler) {
                throw new \LogicException(sprintf(
                    'Several operation handlers support "%s" from workflow "%s": %s and %s.',
                    $operation->transitionName,
                    $operation->workflowName,
                    $matchingHandler::class,
                    $handler::class,
                ));
            }

            $matchingHandler = $handler;
        }

        return $matchingHandler;
    }
}
