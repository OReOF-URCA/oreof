<?php

namespace App\Tests\Unit\Notification;

use App\DTO\ResolvedNotificationPreference;
use App\DTO\WorkFlowData;
use App\Entity\Composante;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Notification;
use App\Entity\Parcours;
use App\Entity\User;
use App\Message\WorkflowEmailNotification;
use App\Notification\NotificationPreferenceResolver;
use App\Notification\WorkflowNotifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class WorkflowNotifierTest extends TestCase
{
    private KernelInterface $kernel;
    private EntityManagerInterface $em;
    private NotificationPreferenceResolver $preferenceResolver;
    private MessageBusInterface $messageBus;
    private Environment $twig;
    private UrlGeneratorInterface $urlGenerator;
    private WorkflowNotifier $notifier;

    public function testBuildPayloadWithWorkflowData(): void
    {
        $parcours = $this->createMock(Parcours::class);
        $parcours->method('getId')->willReturn(42);
        $parcours->method('getLibelle')->willReturn('Parcours Informatique');

        $formation = $this->createMock(Formation::class);
        $formation->method('getId')->willReturn(10);
        $formation->method('getDisplay')->willReturn('Licence Informatique');

        $composante = $this->createMock(Composante::class);
        $composante->method('getId')->willReturn(5);
        $composante->method('getLibelle')->willReturn('UFR Sciences');

        $dpe = $this->createMock(DpeParcours::class);
        $dpe->method('getId')->willReturn(99);

        $wfData = $this->createMock(WorkFlowData::class);
        $wfData->parcours = $parcours;
        $wfData->formation = $formation;
        $wfData->composante = $composante;
        $wfData->dpeParcours = $dpe;
        $wfData->fiche = null;
        $wfData->hasParcours = true;

        $this->urlGenerator->method('generate')
            ->with('parcours_v2_modifier', ['parcours' => 42])
            ->willReturn('/parcours/v2/42/modifier');

        $context = [
            'subject' => '[ORéOF] Parcours validé',
            'data' => $wfData,
            'context' => [
                'motif' => 'Validation sans réserve',
                'dateConseil' => new \DateTimeImmutable('2026-11-14'),
            ],
            'payload' => [
                'customKey' => 'customValue',
            ],
        ];

        $payload = $this->notifier->buildPayload('dpeParcours', 'valider_rf', 'workflow.dpeParcours.transition.valider_rf', $context);

        $this->assertSame('dpeParcours', $payload['workflow']);
        $this->assertSame('valider_rf', $payload['transition']);
        $this->assertSame('workflow.dpeParcours.transition.valider_rf', $payload['eventKey']);
        $this->assertSame('[ORéOF] Parcours validé', $payload['subject']);
        $this->assertSame(42, $payload['parcoursId']);
        $this->assertSame('Parcours Informatique', $payload['parcoursLabel']);
        $this->assertSame(10, $payload['formationId']);
        $this->assertSame('Licence Informatique', $payload['formationLabel']);
        $this->assertSame(5, $payload['composanteId']);
        $this->assertSame('UFR Sciences', $payload['composanteLabel']);
        $this->assertSame(99, $payload['dpeParcoursId']);
        $this->assertTrue($payload['hasParcours']);
        $this->assertSame('Validation sans réserve', $payload['motif']);
        $this->assertSame('14/11/2026', $payload['dateConseil']);
        $this->assertSame('/parcours/v2/42/modifier', $payload['url']);
        $this->assertSame('customValue', $payload['customKey']);
    }

    public function testNotifyCreatesInAppNotificationWithPayload(): void
    {
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $user = new User();
        $user->setEmail('test@example.com');
        $pref = (new \App\Entity\UserNotificationPreference())
            ->setUser($user)
            ->setEmailEnabled(false)
            ->setInAppEnabled(true);
        $user->setNotificationPreference($pref);

        $context = [
            'subject' => '[ORéOF] Soumission parcours',
            'payload' => [
                'parcoursId' => 123,
                'parcoursLabel' => 'Parcours Test',
            ],
        ];

        $persistedNotification = null;
        $this->em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedNotification) {
                if ($entity instanceof Notification) {
                    $persistedNotification = $entity;
                }
            });

        $this->em->expects($this->once())->method('flush');

        $this->notifier->notify([$user], 'workflow.dpeParcours.transition.soumis_parcours', 'dpeParcours', $context);

        $this->assertInstanceOf(Notification::class, $persistedNotification);
        $this->assertSame($user, $persistedNotification->getDestinataire());
        $this->assertSame('notif.dpeParcours.soumis_parcours', $persistedNotification->getTitle());
        $this->assertSame('[ORéOF] Soumission parcours', $persistedNotification->getBody());
        $this->assertIsArray($persistedNotification->getPayload());
        $this->assertSame(123, $persistedNotification->getPayload()['parcoursId']);
        $this->assertSame('Parcours Test', $persistedNotification->getPayload()['parcoursLabel']);
        $this->assertSame('dpeParcours', $persistedNotification->getPayload()['workflow']);
        $this->assertSame('soumis_parcours', $persistedNotification->getPayload()['transition']);
    }

    public function testNotifyDispatchesEmailWithPayloadInTwigContext(): void
    {
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $user = new User();
        $user->setEmail('user@example.com');
        $pref = (new \App\Entity\UserNotificationPreference())
            ->setUser($user)
            ->setEmailEnabled(true)
            ->setInAppEnabled(false);
        $user->setNotificationPreference($pref);

        $renderedContext = null;
        $this->twig->method('render')
            ->willReturnCallback(function ($tpl, $ctx) use (&$renderedContext) {
                $renderedContext = $ctx;
                return '<html>Email body</html>';
            });

        $dispatchedMessage = null;
        $this->messageBus->method('dispatch')
            ->willReturnCallback(function ($msg) use (&$dispatchedMessage) {
                $dispatchedMessage = $msg;
                return new Envelope($msg instanceof Envelope ? $msg->getMessage() : $msg);
            });

        $this->notifier->notify([$user], 'workflow.dpeParcours.transition.valider_rf', 'dpeParcours', [
            'subject' => 'Titre email',
            'payload' => ['custom' => 'val'],
        ]);

        $this->assertInstanceOf(WorkflowEmailNotification::class, $dispatchedMessage);
        $this->assertSame(['user@example.com'], $dispatchedMessage->getRecipients());
        $this->assertSame('Titre email', $dispatchedMessage->getSubject());
        $this->assertSame('val', $renderedContext['payload']['custom'] ?? null);
    }

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->kernel->method('getProjectDir')->willReturn('/tmp');

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->preferenceResolver = new NotificationPreferenceResolver($this->em);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->notifier = new WorkflowNotifier(
            $this->kernel,
            $this->em,
            $this->preferenceResolver,
            $this->messageBus,
            $this->twig,
            $this->urlGenerator
        );
    }
}
