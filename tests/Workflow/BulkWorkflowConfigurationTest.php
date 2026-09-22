<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Form\ChangeRfValidationType;
use App\Workflow\Bulk\BulkWorkflowManager;
use App\Workflow\Form\MetaDrivenFormFactory;
use App\Workflow\Metadata\WorkflowMetaMapper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

final class BulkWorkflowConfigurationTest extends KernelTestCase
{
    public function testTheThreeBusinessWorkflowsAreSupported(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(BulkWorkflowManager::class);
        self::assertInstanceOf(BulkWorkflowManager::class, $manager);

        self::assertTrue($manager->supports(BulkWorkflowManager::DPE_PARCOURS));
        self::assertTrue($manager->supports(BulkWorkflowManager::FICHE));
        self::assertTrue($manager->supports(BulkWorkflowManager::CHANGE_RF));
        self::assertFalse($manager->supports('dpeFormation'));
    }

    public function testMetadataComesFromEachSymfonyWorkflow(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(BulkWorkflowManager::class);

        self::assertSame(
            'valider',
            $manager->transitionMetadata(BulkWorkflowManager::DPE_PARCOURS, 'valider_parcours')['type'] ?? null,
        );
        self::assertSame(
            'valider',
            $manager->transitionMetadata(BulkWorkflowManager::FICHE, 'valider_fiche_compo')['type'] ?? null,
        );
        self::assertSame(
            ChangeRfValidationType::class,
            $manager->transitionMetadata(BulkWorkflowManager::CHANGE_RF, 'valider_ses')['form']['type'] ?? null,
        );
    }

    public function testTheSharedRouteAcceptsEverySupportedWorkflow(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);

        self::assertSame(
            '/workflow/bulk/fiche/valider_fiche_ses',
            $router->generate('workflow_bulk_apply', [
                'workflowName' => BulkWorkflowManager::FICHE,
                'transition' => 'valider_fiche_ses',
            ]),
        );
    }

    public function testChangeRfUsesTheSharedFormFactoryWithItsMetadata(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $manager = $container->get(BulkWorkflowManager::class);
        $mapper = $container->get(WorkflowMetaMapper::class);
        $formFactory = $container->get(MetaDrivenFormFactory::class);

        $metadata = $manager->transitionMetadata(BulkWorkflowManager::CHANGE_RF, 'valider_conseil');
        $mappedMetadata = $mapper->fromArray($metadata);
        self::assertNotNull($mappedMetadata->form);

        $form = $formFactory->create(
            $mappedMetadata->form,
            'valider_conseil',
            $manager->formOptions(BulkWorkflowManager::CHANGE_RF, 'valider_conseil', $metadata),
        );

        self::assertTrue($form->has('date'));
        self::assertTrue($form->has('file'));
        self::assertTrue($form->has('laisserPasser'));
        self::assertFalse($form->has('demandes'));
    }

    public function testTheBulkControllerAcceptsCurrentAndLegacySelectionParameters(): void
    {
        $controller = new \ReflectionClass(\App\Controller\WorkflowBulkController::class);
        $method = $controller->getMethod('selectedIds');
        $instance = $controller->newInstanceWithoutConstructor();

        foreach (['ids', 'parcours', 'fiches', 'demandes'] as $parameter) {
            $request = new Request([$parameter => ['12', '24', '12']]);
            self::assertSame([12, 24], $method->invoke($instance, $request), $parameter);

            $postRequest = new Request([], [$parameter => '12,24,12']);
            $postRequest->setMethod('POST');
            self::assertSame([12, 24], $method->invoke($instance, $postRequest), $parameter.' POST');
        }
    }
}
