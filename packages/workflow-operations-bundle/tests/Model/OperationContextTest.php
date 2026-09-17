<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Tests\Model;

use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use PHPUnit\Framework\TestCase;

final class OperationContextTest extends TestCase
{
    public function testItExposesInputAtTopLevelAndSupportsAliases(): void
    {
        $date = new \DateTimeImmutable('2026-09-17');
        $context = OperationContext::fromInput(
            actor: new \stdClass(),
            input: [
                'date' => $date,
                'argumentaire' => 'À corriger',
            ],
            aliases: ['argumentaire' => 'motif'],
        );

        $workflowContext = $context->workflowContext();

        self::assertSame($date, $workflowContext['date']);
        self::assertSame('À corriger', $workflowContext['motif']);
        self::assertSame($context->input, $workflowContext['input']);
    }

    public function testMetadataOverridesTopLevelInputWithoutChangingStructuredInput(): void
    {
        $context = OperationContext::fromInput(
            actor: null,
            input: ['motif' => 'input'],
            metadata: ['motif' => 'metadata'],
        );

        self::assertSame('metadata', $context->workflowContext()['motif']);
        self::assertSame('input', $context->workflowContext()['input']['motif']);
    }

    public function testExplicitMetadataOverridesAnAlias(): void
    {
        $context = OperationContext::fromInput(
            actor: null,
            input: ['argumentaire' => 'aliased'],
            aliases: ['argumentaire' => 'motif'],
            metadata: ['motif' => 'explicit'],
        );

        self::assertSame('explicit', $context->workflowContext()['motif']);
    }

    public function testReservedKeysCannotBeOverriddenByInputOrMetadata(): void
    {
        $actor = new \stdClass();
        $context = new OperationContext(
            actor: $actor,
            input: ['actor' => 'input actor', 'input' => 'input payload'],
            metadata: ['actor' => 'metadata actor', 'input' => 'metadata payload'],
        );

        self::assertSame($actor, $context->workflowContext()['actor']);
        self::assertSame($context->input, $context->workflowContext()['input']);
    }
}
