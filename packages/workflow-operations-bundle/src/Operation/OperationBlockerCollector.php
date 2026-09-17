<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Operation;

use Dannebicque\WorkflowOperationsBundle\Contract\OperationBlockerProviderInterface;
use Dannebicque\WorkflowOperationsBundle\Model\OperationBlocker;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;

final readonly class OperationBlockerCollector
{
    /** @param iterable<OperationBlockerProviderInterface> $providers */
    public function __construct(private iterable $providers)
    {
    }

    public function collect(
        object $subject,
        WorkflowOperation $operation,
        ?OperationContext $context = null,
    ): OperationBlockerCollection {
        $blockers = [];
        $context ??= OperationContext::empty();

        foreach ($this->providers as $provider) {
            if (!$provider->supports($subject, $operation)) {
                continue;
            }

            foreach ($provider->getBlockers($subject, $operation, $context) as $blocker) {
                if (!$blocker instanceof OperationBlocker) {
                    throw new \UnexpectedValueException(sprintf(
                        '%s::getBlockers() must yield instances of %s.',
                        $provider::class,
                        OperationBlocker::class,
                    ));
                }

                $blockers[] = $blocker;
            }
        }

        return new OperationBlockerCollection($blockers);
    }
}
