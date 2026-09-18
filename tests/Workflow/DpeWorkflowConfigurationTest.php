<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Form\Workflow\ArgumentaireDateType;
use App\Form\Workflow\ArgumentaireType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

final class DpeWorkflowConfigurationTest extends KernelTestCase
{
    public function testEveryReserveTransitionCollectsAndAliasesAnArgumentaire(): void
    {
        self::bootKernel();
        $workflow = self::getContainer()->get('workflow.dpeParcours');

        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $metadata = $workflow->getMetadataStore()->getTransitionMetadata($transition);
            if ('reserver' !== ($metadata['type'] ?? null)) {
                continue;
            }

            self::assertContains(
                $metadata['form']['type'] ?? null,
                [ArgumentaireType::class, ArgumentaireDateType::class],
                sprintf('La transition %s ne collecte pas son argumentaire.', $transition->getName()),
            );
            self::assertSame(
                'motif',
                $metadata['context']['aliases']['argumentaire'] ?? null,
                sprintf('La transition %s ne normalise pas son argumentaire en motif.', $transition->getName()),
            );
        }
    }
}
