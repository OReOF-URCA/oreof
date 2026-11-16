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

use App\DTO\WorkFlowData;
use App\Entity\DpeParcours;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Notification;
use App\Entity\Parcours;
use App\Entity\User;
use App\Message\WorkflowEmailNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
    )
    {
        $this->baseDir = $kernel->getProjectDir();
    }

    public function notify(array $recipients, string $eventKey, string $wf, array $context): void
    {
        $emailDelay = 0;
        $transition = $this->extractTransition($eventKey);

        foreach ($recipients as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $pref = $this->preferenceResolver->resolveFor($user, $wf, $eventKey);
            $payload = $this->buildPayload($wf, $transition, $eventKey, $context, $user);

            // EMAIL
            if ($pref->channelAllowed('email') && null !== $user->getEmail() && '' !== trim($user->getEmail())) {
                $template = file_exists(sprintf('%s/templates/mails/workflow/%s/%s.html.twig', $this->baseDir, $wf, $transition))
                    ? sprintf('mails/workflow/%s/%s.html.twig', $wf, $transition)
                    : 'mails/workflow/default.html.twig';

                $dataArray = [];
                if (isset($context['data'])) {
                    if (is_object($context['data']) && method_exists($context['data'], 'toArray')) {
                        $dataArray = $context['data']->toArray();
                    } elseif (is_array($context['data'])) {
                        $dataArray = $context['data'];
                    }
                }

                $html = $this->twig->render(
                    $template,
                    array_merge([
                        'user' => $user,
                        'wf' => $wf,
                        'eventKey' => $transition,
                        'payload' => $payload,
                        'path' => sprintf('%s/templates/mails/workflow/%s/%s.html.twig', $this->baseDir, $wf, $transition),
                    ], $dataArray, $context['context'] ?? [])
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

                $title = $context['payload']['title'] ?? ('notif.' . $wf . '.' . $transition);
                $body = $context['payload']['body'] ?? ($context['subject'] ?? ('notif.' . $wf . '.' . $transition));

                $n->setTitle($title);
                $n->setBody($body);
                $n->setPayload($payload);
                $this->em->persist($n);
            }
        }
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function buildPayload(string $wf, string $transition, string $eventKey, array $context, ?User $user = null): array
    {
        $explicitPayload = is_array($context['payload'] ?? null) ? $context['payload'] : [];

        $payload = [
            'workflow' => $wf,
            'transition' => $transition,
            'eventKey' => $eventKey,
        ];

        if (isset($context['subject']) && is_string($context['subject'])) {
            $payload['subject'] = $context['subject'];
        }

        // Extract entity data
        $data = $context['data'] ?? null;
        if ($data instanceof WorkFlowData) {
            if ($data->formation) {
                $payload['formationId'] = $data->formation->getId();
                $payload['formationLabel'] = $data->formation->getDisplay();
            }
            if ($data->parcours) {
                $payload['parcoursId'] = $data->parcours->getId();
                $payload['parcoursLabel'] = $data->parcours->getLibelle();
            }
            $payload['hasParcours'] = (bool)$data->hasParcours;
            if ($data->composante) {
                $payload['composanteId'] = $data->composante->getId();
                $payload['composanteLabel'] = $data->composante->getLibelle();
            }
            if ($data->dpeParcours) {
                $payload['dpeParcoursId'] = $data->dpeParcours->getId();
            }
            if ($data->fiche) {
                $payload['ficheId'] = $data->fiche->getId();
                $payload['ficheLabel'] = $data->fiche->getLibelle();
            }
        } elseif ($data instanceof DpeParcours) {
            $payload['dpeParcoursId'] = $data->getId();
            $parcours = $data->getParcours();
            if ($parcours) {
                $payload['parcoursId'] = $parcours->getId();
                $payload['parcoursLabel'] = $parcours->getLibelle();
                $formation = $parcours->getFormation();
                if ($formation) {
                    $payload['formationId'] = $formation->getId();
                    $payload['formationLabel'] = $formation->getDisplay();
                }
            }
        } elseif ($data instanceof Parcours) {
            $payload['parcoursId'] = $data->getId();
            $payload['parcoursLabel'] = $data->getLibelle();
            $formation = $data->getFormation();
            if ($formation) {
                $payload['formationId'] = $formation->getId();
                $payload['formationLabel'] = $formation->getDisplay();
            }
        } elseif ($data instanceof Formation) {
            $payload['formationId'] = $data->getId();
            $payload['formationLabel'] = $data->getDisplay();
        } elseif ($data instanceof FicheMatiere) {
            $payload['ficheId'] = $data->getId();
            $payload['ficheLabel'] = $data->getLibelle();
        } elseif (is_array($data)) {
            foreach (['formationId', 'formationLabel', 'parcoursId', 'parcoursLabel', 'composanteId', 'composanteLabel', 'dpeParcoursId', 'ficheId', 'ficheLabel'] as $k) {
                if (isset($data[$k])) {
                    $payload[$k] = $data[$k];
                }
            }
        }

        // Extract transition context details (e.g. motif, dateConseil, etc.)
        $transitionContext = is_array($context['context'] ?? null) ? $context['context'] : [];
        if (isset($transitionContext['motif']) || isset($transitionContext['argumentaire'])) {
            $payload['motif'] = (string)($transitionContext['motif'] ?? $transitionContext['argumentaire']);
        }
        foreach (['dateConseil', 'dateCfvu', 'datePublication', 'date'] as $dateKey) {
            if (isset($transitionContext[$dateKey])) {
                $val = $transitionContext[$dateKey];
                $payload[$dateKey] = $val instanceof \DateTimeInterface ? $val->format('d/m/Y') : (string)$val;
            }
        }

        // Generate target URL if not provided
        if (!isset($explicitPayload['url'])) {
            $url = null;
            if (!empty($payload['parcoursId'])) {
                if ($this->urlGenerator !== null) {
                    try {
                        $url = $this->urlGenerator->generate('parcours_v2_modifier', ['parcours' => $payload['parcoursId']]);
                    } catch (\Throwable) {
                        $url = '/parcours/v2/' . $payload['parcoursId'] . '/modifier';
                    }
                } else {
                    $url = '/parcours/v2/' . $payload['parcoursId'] . '/modifier';
                }
            } elseif (!empty($payload['ficheId'])) {
                $url = '/fiche-matiere/v2/' . $payload['ficheId'];
            }
            if ($url !== null) {
                $payload['url'] = $url;
            }
        }

        return array_merge($payload, $explicitPayload);
    }

    private function extractTransition(string $eventKey): string
    {
        $parts = explode('.', $eventKey);
        return $parts[count($parts) - 1] ?? 'default';
    }
}
