<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Tests\Model;

use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use PHPUnit\Framework\TestCase;

final class OperationContextTest extends TestCase
{
    public function testRuntimeDataIsNeverExposedToSymfonyWorkflow(): void
    {
        $actor = new \stdClass();
        $context = OperationContext::fromInput(
            actor: $actor,
            input: ['argumentaire' => 'À corriger'],
            aliases: ['argumentaire' => 'motif'],
            metadata: ['source' => 'test'],
        )->withRuntime([
            'previous_place' => 'soumis_ses',
            'request' => new \stdClass(),
        ]);

        $workflowContext = $context->workflowContext();

        self::assertSame('À corriger', $workflowContext['argumentaire']);
        self::assertSame('À corriger', $workflowContext['motif']);
        self::assertSame('test', $workflowContext['source']);
        self::assertSame($actor, $workflowContext['actor']);
        self::assertArrayNotHasKey('runtime', $workflowContext);
        self::assertArrayNotHasKey('request', $workflowContext);
        self::assertSame('soumis_ses', $context->runtime['previous_place']);
    }
}
