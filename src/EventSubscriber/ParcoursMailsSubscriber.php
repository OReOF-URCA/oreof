<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/ParcoursMailsSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 19/09/2025 19:59
 */

namespace App\EventSubscriber;

use App\Classes\Mailer;
use App\Events\ParcoursEvent;
use App\EventSubscriber\DpeWorkflow\AbstractDpeMailSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ParcoursMailsSubscriber extends AbstractDpeMailSubscriber implements EventSubscriberInterface
{

    public function __construct(
        protected Mailer $myMailer
    )
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            ParcoursEvent::class => 'onCreated'
        ];
    }

    public function onCreated(ParcoursEvent $event): void
    {
        $parcours = $event->getParcours();
        $formation = $parcours->getFormation();

        if (null === $formation) {
            return;
        }

        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/parcours/created.html.twig',
            ['parcours' => $parcours, 'formation' => $formation]
        );
        $this->myMailer->sendMessage(
            [$formation->getResponsableMention()?->getEmail(), $formation->getCoResponsable()?->getEmail(),
                $parcours->getRespParcours()?->getEmail(),
                $parcours->getCoResponsable()?->getEmail(),
                $formation->getComposantePorteuse()?->getResponsableDpe()?->getEmail(),
                self::EMAIL_CENTRAL
            ],
            '[ORéOF]  Un parcours de votre formation a été soumis à validation'
        );
    }
}
