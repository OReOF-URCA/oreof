<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Form\Workflow\ArgumentaireType;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

final class FicheWorkflowConfigurationTest extends KernelTestCase
{
    public function testEveryTransitionDeclaresItsDisplayType(): void
    {
        self::bootKernel();
        $workflow = self::getContainer()->get('workflow.fiche');
        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $metadata = $workflow->getMetadataStore()->getTransitionMetadata($transition);

            self::assertContains(
                $metadata['type'] ?? null,
                ['valider', 'reserver', 'refuser'],
                sprintf('La transition %s ne déclare pas son type d\'affichage.', $transition->getName()),
            );
        }
    }

    public function testEveryTransitionUsesTheFicheOperationVoter(): void
    {
        self::bootKernel();
        $workflow = self::getContainer()->get('workflow.fiche');
        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        $factory = new WorkflowOperationFactory();
        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $operation = $factory->create($workflow, $transition->getName());

            self::assertSame(
                'FICHE_WORKFLOW_TRANSITION',
                $operation->metadata['authorization']['attribute'] ?? null,
                sprintf('La transition %s ne déclare pas le voter des fiches matières.', $transition->getName()),
            );
            self::assertSame('operation', $operation->metadata['authorization']['subject'] ?? null);
        }
    }

    public function testReserveTransitionCollectsAndAliasesItsArgumentaire(): void
    {
        self::bootKernel();
        $workflow = self::getContainer()->get('workflow.fiche');
        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $metadata = $workflow->getMetadataStore()->getTransitionMetadata($transition);
            if ('reserver' !== ($metadata['type'] ?? null)) {
                continue;
            }

            self::assertSame(ArgumentaireType::class, $metadata['form']['type'] ?? null);
            self::assertSame('motif', $metadata['context']['aliases']['argumentaire'] ?? null);
        }
    }

    public function testOnlyForwardValidationTransitionsDisplayTheValidationReport(): void
    {
        self::bootKernel();
        $workflow = self::getContainer()->get('workflow.fiche');
        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        $metadataByTransition = [];
        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $metadataByTransition[$transition->getName()] = $workflow
                ->getMetadataStore()
                ->getTransitionMetadata($transition);
        }

        foreach (['valider_fiche_compo', 'valider_fiche_ses', 'publier'] as $transition) {
            self::assertTrue($metadataByTransition[$transition]['validation']['display'] ?? false);
        }

        foreach (['rouvrir_fiche_matiere', 'rouvrir_fiche_matiere_b'] as $transition) {
            self::assertFalse($metadataByTransition[$transition]['validation']['display'] ?? false);
        }
    }
}
