<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Tests\Operation;

use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Metadata\MetadataStoreInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class WorkflowOperationFactoryTest extends TestCase
{
    public function testItMergesWorkflowDefaultsWithTransitionMetadata(): void
    {
        $transition = new Transition('publish', 'review', 'published');
        $definition = new Definition(['review', 'published'], [$transition]);

        $metadataStore = $this->createStub(MetadataStoreInterface::class);
        $metadataStore->method('getWorkflowMetadata')->willReturn([
            'operation_defaults' => [
                'authorization' => [
                    'attribute' => 'DOCUMENT_TRANSITION',
                    'subject' => 'operation',
                    'reason' => 'document.denied',
                ],
                'button_class' => 'secondary',
            ],
        ]);
        $metadataStore->method('getTransitionMetadata')->willReturn([
            'button_class' => 'success',
            'authorization' => ['reason' => 'document.publish.denied'],
        ]);

        $workflow = $this->createStub(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('document');
        $workflow->method('getDefinition')->willReturn($definition);
        $workflow->method('getMetadataStore')->willReturn($metadataStore);

        $operation = (new WorkflowOperationFactory())->create($workflow, 'publish');

        self::assertSame('success', $operation->metadata['button_class']);
        self::assertSame([
            'attribute' => 'DOCUMENT_TRANSITION',
            'subject' => 'operation',
            'reason' => 'document.publish.denied',
        ], $operation->metadata['authorization']);
    }
}
