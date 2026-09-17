<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Tests\Operation;

use Dannebicque\WorkflowOperationsBundle\Contract\OperationHandlerInterface;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Dannebicque\WorkflowOperationsBundle\Operation\OperationHandlerRegistry;
use PHPUnit\Framework\TestCase;

final class OperationHandlerRegistryTest extends TestCase
{
    public function testItReturnsTheOnlySupportingHandler(): void
    {
        $expected = $this->handler(true);
        $registry = new OperationHandlerRegistry([$this->handler(false), $expected]);

        self::assertSame(
            $expected,
            $registry->find(new \stdClass(), new WorkflowOperation('document', 'publish')),
        );
    }

    public function testItRejectsAmbiguousHandlers(): void
    {
        $registry = new OperationHandlerRegistry([$this->handler(true), $this->handler(true)]);

        $this->expectException(\LogicException::class);
        $registry->find(new \stdClass(), new WorkflowOperation('document', 'publish'));
    }

    private function handler(bool $supports): OperationHandlerInterface
    {
        return new class($supports) implements OperationHandlerInterface {
            public function __construct(private readonly bool $supported)
            {
            }

            public function supports(object $subject, WorkflowOperation $operation): bool
            {
                return $this->supported;
            }

            public function handle(
                object $subject,
                WorkflowOperation $operation,
                OperationContext $context,
            ): void {
            }
        };
    }
}
