<?php

namespace App\Tests\Workflow;

use App\Form\ChangeRfValidationType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

final class ChangeRfWorkflowConfigurationTest extends KernelTestCase
{
    public function testTransitionGraphMatchesTheBusinessProcess(): void
    {
        self::bootKernel();
        $workflow = self::getContainer()->get('workflow.changeRf');

        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        $expected = [
            'effectuer_demande' => [['demande_initialisee'], ['soumis_conseil']],
            'valider_conseil' => [['soumis_conseil'], ['soumis_ses']],
            'valider_ses' => [['soumis_ses'], ['soumis_cfvu']],
            'reserver_ses' => [['soumis_ses'], ['soumis_conseil']],
            'valider_cfvu_avec_pv' => [['soumis_cfvu'], ['effectuee']],
            'valider_cfvu_attente_pv' => [['soumis_cfvu'], ['attente_pv']],
            'reserver_cfvu' => [['soumis_cfvu'], ['soumis_conseil']],
            'deposer_pv' => [['attente_pv'], ['verification_pv']],
            'valider_pv' => [['verification_pv'], ['effectuee']],
        ];

        $actual = [];
        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $actual[$transition->getName()] = [$transition->getFroms(), $transition->getTos()];
        }

        self::assertSame($expected, $actual);
    }

    public function testInteractiveTransitionsDeclareTheirFormType(): void
    {
        self::bootKernel();
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('workflow.changeRf');
        $metadataStore = $workflow->getMetadataStore();

        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            if ('effectuer_demande' === $transition->getName()) {
                continue;
            }

            $metadata = $metadataStore->getTransitionMetadata($transition);
            self::assertSame(
                ChangeRfValidationType::class,
                $metadata['form']['type'] ?? null,
                sprintf('Transition %s has no ChangeRf form type.', $transition->getName()),
            );
        }
    }

    public function testPvReviewStatesAreVisibleProcessSteps(): void
    {
        self::bootKernel();
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('workflow.changeRf');
        $metadataStore = $workflow->getMetadataStore();

        self::assertTrue($metadataStore->getPlaceMetadata('attente_pv')['process'] ?? false);
        self::assertTrue($metadataStore->getPlaceMetadata('verification_pv')['process'] ?? false);
    }
}
