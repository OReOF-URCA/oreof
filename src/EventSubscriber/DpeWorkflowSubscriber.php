<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/DpeWorkflowSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 03/10/2025 12:58
 */

namespace App\EventSubscriber;

use App\DTO\WorkFlowData;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;
use App\Notification\WorkflowNotifier;
use App\Workflow\RecipientResolver;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\WorkflowInterface;

class DpeWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WorkflowInterface $dpeParcoursWorkflow,
        private readonly WorkflowNotifier  $notifier,
        private readonly RecipientResolver $recipients
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.dpeParcours.transition' => 'onTransition',
//            'workflow.dpeParcours.guard'     => 'onGuard',
        ];
    }

    public function onTransition(TransitionEvent $event): void
    {
        $subject = $event->getSubject();
        $data = new WorkflowData($subject);
        $transition = $event->getTransition();
        if (null === $subject || null === $transition) {
            return;
        }
        $meta = $this->dpeParcoursWorkflow->getMetadataStore()->getTransitionMetadata($transition) ?? [];
        $eventKey = sprintf('workflow.dpeParcours.transition.%s', $transition->getName());
        $context = [
            'subject' => '[ORéOF] ' . $data->getTitre($meta),
            'data' => $data,
            'context' => $event->getContext() ?? [],
        ];
        dump($event);
        dump($context);
        $recipients = $this->recipients->resolveRecipients('dpeParcours', $transition->getName(), $data);
        dump($recipients);
        $this->notifier->notify($recipients['recipients'], $eventKey, $this->dpeParcoursWorkflow->getName(), $context);
    }
}
