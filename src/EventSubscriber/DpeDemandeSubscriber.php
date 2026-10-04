<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/DpeDemandeSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 30/01/2024 08:55
 */

namespace App\EventSubscriber;

use App\Classes\Mailer;
use App\Events\DpeDemandeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DpeDemandeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected Mailer $mailer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DpeDemandeEvent::DPE_DEMANDE_OPENED => 'onDpeDemandeOpened',
            DpeDemandeEvent::DPE_DEMANDE_UPDATED => 'onDpeDemandeUpdated'
        ];
    }

    public function onDpeDemandeOpened(DpeDemandeEvent $event): void
    {
        $dpeDemande = $event->getDpeDemande();
        $user = $event->getUser();
        if ($user === null || $user->getEmail() === null) {
            return;
        }

        $this->mailer->initEmail();
        $this->mailer->setTemplate(
            'mails/dpe/dpe_demande_created.html.twig',
            ['dpeDemande' => $dpeDemande]
        );
        $this->mailer->sendMessage(['oreof@univ-reims.fr', $user->getEmail()], '[ORéOF] Réouverture de DPE');
        $dpe = $dpeDemande->getFormation()?->getComposantePorteuse()?->getResponsableDpe();
        $mails = [
            $user->getEmail()
        ];
        $withDpe = false;

        if ($dpe !== null && $dpe->getId() !== $user->getId() && $dpe->getEmail() !== null) {
            $mails[] = $dpe->getEmail();
            $withDpe = true;
        }
        $this->mailer->initEmail();
        $this->mailer->setTemplate(
            'mails/dpe/dpe_demande_created_demandeur.html.twig',
            [
                'dpeDemande' => $dpeDemande,
                'withDpe' => $withDpe
            ]
        );


        $this->mailer->sendMessage($mails, '[ORéOF] Réouverture de DPE');
    }

    public function onDpeDemandeUpdated(DpeDemandeEvent $event): void
    {
        $dpeDemande = $event->getDpeDemande();
        $user = $event->getUser();
        if ($user === null || $user->getEmail() === null) {
            return;
        }

        $this->mailer->initEmail();
        $this->mailer->setTemplate(
            'mails/dpe/dpe_demande_updated.txt.twig',
            ['dpeDemande' => $dpeDemande]
        );
        $this->mailer->sendMessage([$user->getEmail()], '[ORéOF] Mise à jour de la demande de DPE');
    }
}
