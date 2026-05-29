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

use App\Classes\Mailer;
use App\Entity\User;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;

class WorkflowNotifier
{
    private string $baseDir;

    public function __construct(
        KernelInterface $kernel,
        private readonly Mailer                         $myMailer,
        private readonly EntityManagerInterface         $em,
        private readonly NotificationPreferenceResolver $preferenceResolver,
    )
    {
        $this->baseDir = $kernel->getProjectDir();
    }

    public function notify(array $recipients, string $eventKey, string $wf, array $context): void
    {

        foreach ($recipients as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $pref = $this->preferenceResolver->resolveFor($user, $wf, $eventKey);

            // EMAIL
            if ($pref->channelAllowed('email')) {
                $this->myMailer->initEmail();
                $this->myMailer->setTemplate(
                    file_exists(sprintf('%s/templates/mails/workflow/%s/%s.html.twig', $this->baseDir, $wf, $this->extractTransition($eventKey)))
                        ? 'mails/workflow/' . $wf . '/' . $this->extractTransition($eventKey) . '.html.twig'
                        : 'mails/workflow/default.html.twig',
                    array_merge(
                        [
                            'user' => $user,
                            'wf' => $wf,
                            'eventKey' => $this->extractTransition($eventKey),
                            'path' => sprintf('%s/templates/mails/workflow/%s/%s.html.twig', $this->baseDir, $wf, $this->extractTransition($eventKey))
                        ],
                        $context['data']->toArray(),
                        $context['context'] ?? []
                    )
                );

                try {
                    $this->myMailer->sendMessage(
                        [$user->getEmail()],
                        $context['subject'] ?? '[ORéOF] - ' . $this->extractTransition($eventKey)
                    );
                } catch (Exception $e) {
                    throw new RuntimeException('Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
                }
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
