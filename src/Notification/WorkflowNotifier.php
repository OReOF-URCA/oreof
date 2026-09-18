<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Notification/WorkflowNotifier.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 03/10/2025 12:56
 */

// src/Notification/WorkflowNotifier.php
namespace App\Notification;

use App\Entity\User;
use App\Entity\Notification;
use App\Message\WorkflowEmailNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

class WorkflowNotifier
{
    private string $baseDir;

    public function __construct(
        KernelInterface $kernel,
        private readonly EntityManagerInterface         $em,
        private readonly NotificationPreferenceResolver $preferenceResolver,
        private readonly MessageBusInterface             $messageBus,
        private readonly Environment                     $twig,
    )
    {
        $this->baseDir = $kernel->getProjectDir();
    }

    public function notify(array $recipients, string $eventKey, string $wf, array $context): void
    {
        $emailDelay = 0;

        foreach ($recipients as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $pref = $this->preferenceResolver->resolveFor($user, $wf, $eventKey);
            // EMAIL
            if ($pref->channelAllowed('email') && null !== $user->getEmail() && '' !== trim($user->getEmail())) {
                $transition = $this->extractTransition($eventKey);
                $template = file_exists(sprintf('%s/templates/mails/workflow/%s/%s.html.twig', $this->baseDir, $wf, $transition))
                    ? sprintf('mails/workflow/%s/%s.html.twig', $wf, $transition)
                    : 'mails/workflow/default.html.twig';
                $html = $this->twig->render(
                    $template,
                    array_merge([
                        'user' => $user,
                        'wf' => $wf,
                        'eventKey' => $transition,
                        'path' => sprintf('%s/templates/mails/workflow/%s/%s.html.twig', $this->baseDir, $wf, $transition),
                    ], $context['data']->toArray(), $context['context'] ?? [])
                );
                $this->messageBus->dispatch(
                    new WorkflowEmailNotification(
                        [$user->getEmail()],
                        $context['subject'] ?? '[ORéOF] - ' . $transition,
                        $html,
                    ),
                    [new DelayStamp($emailDelay)],
                );
                $emailDelay += 1500;
            }

            // IN-APP
            if ($pref->channelAllowed('inapp')) {
                $n = new Notification();
                $n->setDestinataire($user);
                $n->setTitle('notif.' . $wf . '.' . $this->extractTransition($eventKey));
                $n->setBody('notif.' . $wf . '.' . $this->extractTransition($eventKey));
                $n->setPayload([]); //todo: a faire
                $this->em->persist($n);
            }
        }
        $this->em->flush();
    }

    private function extractTransition(string $eventKey): string
    {
        $parts = explode('.', $eventKey);
        return $parts[count($parts) - 1] ?? 'default';
    }
}
