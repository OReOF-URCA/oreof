<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Tests\Operation;

use Dannebicque\WorkflowOperationsBundle\Operation\OperationContextNormalizer;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Metadata\MetadataStoreInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class OperationContextNormalizerTest extends TestCase
{
    public function testItReadsAliasesFromTransitionMetadata(): void
    {
        $transition = new Transition('reserve', 'review', 'draft');
        $definition = new Definition(['review', 'draft'], [$transition]);

        $metadataStore = $this->createStub(MetadataStoreInterface::class);
        $metadataStore->method('getTransitionMetadata')->willReturn([
            'context' => [
                'aliases' => ['argumentaire' => 'motif'],
            ],
        ]);

        $workflow = $this->createStub(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('document');
        $workflow->method('getDefinition')->willReturn($definition);
        $workflow->method('getMetadataStore')->willReturn($metadataStore);

        $context = (new OperationContextNormalizer(new WorkflowOperationFactory()))->normalize(
            $workflow,
            'reserve',
            null,
            ['argumentaire' => 'Document incomplet'],
        );

        self::assertSame('Document incomplet', $context->workflowContext()['motif']);
    }
}
