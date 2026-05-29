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
use Symfony\Component\Workflow\WorkflowInterface;

class DpeWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WorkflowInterface $dpeParcoursWorkflow,
        private WorkflowNotifier  $notifier,
        private RecipientResolver $recipients
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

    public function onTransition(Event $event): void
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
        $recipients = $this->recipients->resolveRecipients('dpeParcours', $transition->getName(), $data);
        $this->notifier->notify($recipients['recipients'], $eventKey, $this->dpeParcoursWorkflow->getName(), $context);
    }

//    public function onGuard(GuardEvent $event): void
//    {
//        $transition = $event->getTransition();
//        $wf = $event->getWorkflow();
//        $meta = $wf->getMetadataStore()->getTransitionMetadata($transition) ?? [];
//
//        // Exemple de guard générique : une propriété doit être remplie avant 'valider_*'
//        if (($meta['type'] ?? null) === 'valider') {
//            $subject = $event->getSubject();
//            if (method_exists($subject, 'isReadyForValidation') && !$subject->isReadyForValidation()) {
//                $event->setBlocked(true);
//                $event->addTransitionBlocker(new \Symfony\Component\Workflow\TransitionBlocker(
//                    "Des informations obligatoires manquent avant validation.", 'missing_data'
//                ));
//            }
//        }
//        // Tu peux aussi appliquer des guard par rôle/meta: $meta['required_role'] etc.
//    }
}
