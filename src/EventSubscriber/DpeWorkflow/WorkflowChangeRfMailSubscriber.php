<?php

namespace App\EventSubscriber\DpeWorkflow;

use App\Entity\ChangeRf;
use App\Message\ChangeRfWorkflowNotification;
use DateTimeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\Event;

readonly class WorkflowChangeRfMailSubscriber implements EventSubscriberInterface
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.changeRf.completed.valider_conseil' => 'onTransitionCompleted',
            'workflow.changeRf.completed.valider_ses' => 'onTransitionCompleted',
            'workflow.changeRf.completed.reserver_ses' => 'onTransitionCompleted',
            'workflow.changeRf.completed.valider_cfvu_avec_pv' => 'onTransitionCompleted',
            'workflow.changeRf.completed.reserver_cfvu' => 'onTransitionCompleted',
            'workflow.changeRf.completed.valider_cfvu_attente_pv' => 'onTransitionCompleted',
            'workflow.changeRf.completed.deposer_pv' => 'onTransitionCompleted',
        ];
    }

    public function onTransitionCompleted(Event $event): void
    {
        $demande = $event->getSubject();
        if (!$demande instanceof ChangeRf || null === $demande->getId()) {
            return;
        }

        $context = $event->getContext();
        $date = $context['date'] ?? null;

        $this->messageBus->dispatch(new ChangeRfWorkflowNotification(
            $demande->getId(),
            $event->getTransition()->getName(),
            isset($context['motif']) ? (string) $context['motif'] : null,
            $date instanceof DateTimeInterface ? $date->format(DATE_ATOM) : (is_string($date) ? $date : null),
        ));
    }
}
