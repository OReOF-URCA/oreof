<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Tests\Operation;

use Dannebicque\WorkflowOperationsBundle\Contract\OperationBlockerProviderInterface;
use Dannebicque\WorkflowOperationsBundle\Model\BlockerSeverity;
use Dannebicque\WorkflowOperationsBundle\Model\OperationBlocker;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Dannebicque\WorkflowOperationsBundle\Operation\OperationBlockerCollector;
use PHPUnit\Framework\TestCase;

final class OperationBlockerCollectorTest extends TestCase
{
    public function testItCollectsOnlySupportedProviders(): void
    {
        $supported = $this->provider(true, [
            new OperationBlocker('missing_document', 'A document is missing.'),
            new OperationBlocker('description', 'Description is incomplete.', BlockerSeverity::Warning),
        ]);
        $unsupported = $this->provider(false, [
            new OperationBlocker('ignored', 'This blocker must be ignored.'),
        ]);

        $collection = (new OperationBlockerCollector([$supported, $unsupported]))->collect(
            new \stdClass(),
            new WorkflowOperation('document', 'publish'),
        );

        self::assertCount(2, $collection);
        self::assertTrue($collection->hasBlockingItems());
        self::assertSame('missing_document', $collection->all()[0]->code);
    }

    /** @param list<OperationBlocker> $blockers */
    private function provider(bool $supports, array $blockers): OperationBlockerProviderInterface
    {
        return new class($supports, $blockers) implements OperationBlockerProviderInterface {
            public function __construct(
                private readonly bool $supported,
                private readonly array $blockers,
            ) {
            }

            public function supports(object $subject, WorkflowOperation $operation): bool
            {
                return $this->supported;
            }

            public function getBlockers(
                object $subject,
                WorkflowOperation $operation,
                OperationContext $context,
            ): iterable {
                yield from $this->blockers;
            }
        };
    }
}
