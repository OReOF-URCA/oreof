<?php

namespace App\Tests\Unit\Notification;

use App\DTO\ResolvedNotificationPreference;
use App\Entity\DpeParcours;
use App\Entity\User;
use App\Notification\NotificationPreferenceResolver;
use App\Notification\NotificationSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationSettingsServiceTest extends TestCase
{
    public function testGetWorkflowsDataReturnsConfiguredWorkflowsWithTranslatedTransitions(): void
    {
        $registry = $this->createStub(Registry::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $em->method('getRepository')->willReturn($repo);

        $resolver = new NotificationPreferenceResolver($em);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(function ($key, $params = [], $domain = null) {
            return match ($key) {
                'workflow.valider_rf.menu.label' => 'Valider le parcours',
                'validation.parcours_rf' => 'Validation du parcours',
                default => $key,
            };
        });

        // Setup mock workflow
        $transition = new Transition('valider_rf', 'en_cours_redaction', 'soumis_parcours');
        $places = ['en_cours_redaction'];

        $metadataStore = new InMemoryMetadataStore(
            workflowMetadata: [],
            placesMetadata: [
                'en_cours_redaction' => [
                    'process' => true,
                    'label' => 'validation.parcours_rf',
                ],
            ],
            transitionsMetadata: new \SplObjectStorage()
        );

        $definition = new Definition($places, [$transition], null, $metadataStore);
        $workflow = new Workflow($definition, null, null, 'dpeParcours');

        $registry->method('get')->willReturn($workflow);

        $service = new NotificationSettingsService($registry, $em, $resolver, $translator);

        $user = new User();
        $workflows = $service->getWorkflowsData($user, ['dpeParcours']);

        $this->assertCount(1, $workflows);
        $wf = $workflows[0];
        $this->assertSame('dpeParcours', $wf['name']);
        $this->assertSame('Parcours DPE', $wf['label']);
        $this->assertSame('ph:tree-structure', $wf['icon']);
        $this->assertNotEmpty($wf['description']);

        $this->assertCount(1, $wf['places']);
        $place = $wf['places'][0];
        $this->assertSame('en_cours_redaction', $place['name']);
        $this->assertSame('Validation du parcours', $place['label']);

        $this->assertCount(1, $place['transitions']);
        $t = $place['transitions'][0];
        $this->assertSame('valider_rf', $t['name']);
        $this->assertSame('Valider le parcours', $t['label']);
    }
}
