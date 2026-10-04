<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/WorkflowEcMailSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\EventSubscriber;

use App\Classes\Mailer;
use App\Repository\ComposanteRepository;
use App\Repository\FormationRepository;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;

class WorkflowEcMailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected UserRepository $userRepository,
        protected ComposanteRepository $composanteRepository,
        protected FormationRepository $formationRepository,
        protected Mailer $myMailer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.fiche.transition.reserver_fiche_ses' => 'onReserveFiche',

        ];
    }

    public function onReserveFiche(TransitionEvent $event): void
    {
        $fiche = $event->getSubject();
        $parcours = $fiche->getParcours();
        $formation = $parcours->getFormation();
        $context = $event->getContext();

        if ($formation === null) {
            return;
        }
        //todo: check si le responsable de formation accepte le mail
        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/fiche/reserve_central.html.twig',
            ['fiche' => $fiche, 'parcours' => $parcours, 'formation' => $formation, 'motif' => $context['motif']]
        );
        $this->myMailer->sendMessage(
            [
                $formation->getCoResponsable()?->getEmail(),
                $formation->getResponsableMention()?->getEmail(),
                $parcours->getRespParcours()?->getEmail(),
                $parcours->getCoResponsable()?->getEmail()
            ],
            '[ORéOF]  Une fiche de votre parcours ou formation a reçu des réserves',
            ['replyTo' => Mailer::MAIL_GENERIC, 'cc' => Mailer::MAIL_GENERIC]
        );
    }


}
