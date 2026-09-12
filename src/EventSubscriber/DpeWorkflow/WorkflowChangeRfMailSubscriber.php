<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/DpeWorkflow/WorkflowParcoursMailSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/10/2023 10:52
 */

namespace App\EventSubscriber\DpeWorkflow;

use App\Classes\Mailer;
use App\Repository\ComposanteRepository;
use App\Repository\FormationRepository;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

class WorkflowChangeRfMailSubscriber extends AbstractDpeMailSubscriber implements EventSubscriberInterface
{
    //todo : WORKFLOW DE CHANGEMENT DE RF...
    public function __construct(
        protected UserRepository       $userRepository,
        protected ComposanteRepository $composanteRepository,
        protected FormationRepository  $formationRepository,
        protected Mailer               $myMailer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.changeRf.transition.valider_conseil' => 'onValiderConseil', //mail au SES
            'workflow.changeRf.transition.valider_ses' => 'onValiderSes', //mail au DPE
            'workflow.changeRf.transition.reserver_ses' => 'onReserverSes', //mail au DPE avec motif
            'workflow.changeRf.transition.valider_cfvu_avec_pv' => 'onValiderCfvu', //mail au DPE + rf
            'workflow.changeRf.transition.reserver_cfvu' => 'onReserveCfvu', //mail au DPE
            'workflow.changeRf.transition.valider_cfvu_attente_pv' => 'onValiderCfvuAttentePv', //mail DPE attente PV
            'workflow.changeRf.transition.deposer_pv' => 'onDeposePv', //mail au ses
        ];
    }

    public function onValiderConseil(Event $event): void
    {
        $data = $this->getDataFromChangeRfEvent($event);
        if ($data === null) {
            return;
        }

        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/changerf/valider_conseil.html.twig',
            $this->getDataChangeRf()
        );
        $this->myMailer->sendMessage(
            [$this->getEmailOreof(), $this->getEmailCentral()],
            '[ORéOF]  Un changement de responsable de formation a été soumis'
        );
    }

    public function onValiderSes(Event $event): void
    {
        $data = $this->getDataFromChangeRfEvent($event);
        if ($data === null) {
            return;
        }
//todo: mail aux chef ancien et nouveau + SES
        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/changerf/valider_ses.html.twig',
            $this->getDataChangeRf()
        );

        $this->myMailer->sendMessage(
            [$this->responsableDpe->getEmail()],
            '[ORéOF]  Un changement de responsable de formation a été validé par le SES'
        );
    }

    public function onReserverSes(Event $event): void
    {
        $data = $this->getDataFromChangeRfEvent($event);
        $context = $event->getContext();
        if ($data === null) {
            return;
        }

        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/changerf/reserver_ses.html.twig',
            array_merge($this->getDataChangeRf(), ['motif' => $context['motif']])
        );
        $this->myMailer->sendMessage(
            [$this->responsableDpe->getEmail()],
            '[ORéOF]  Des réserves ont été émises sur un changement de responsable de formation'
        );
    }

    public function onValiderCfvu(Event $event): void
    {
        $data = $this->getDataFromChangeRfEvent($event);
        $context = $event->getContext();
        if ($data === null) {
            return;
        }

        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/changerf/valider_cfvu_avec_pv.html.twig',
            array_merge($this->getDataChangeRf(), ['dateCfvu' => $context['date'],])
        );
        $this->myMailer->sendMessage(
            [$this->responsableDpe->getEmail(), $this->formation->getResponsableMention()?->getEmail()],
            '[ORéOF]  Un changement de responsable de formation a été validé par la CFVU'
        );
    }

    public function onValiderCfvuAttentePv(Event $event): void
    {
        $data = $this->getDataFromChangeRfEvent($event);
        $context = $event->getContext();
        if ($data === null) {
            return;
        }

        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/changerf/valider_cfvu_attente_pv.html.twig',
            array_merge($this->getDataChangeRf(), ['dateCfvu' => $context['date'],])
        );
        $this->myMailer->sendMessage(
            [$this->responsableDpe->getEmail(), $this->formation->getResponsableMention()?->getEmail()],
            '[ORéOF]  Un changement de responsable de formation a été validé par la CFVU'
        );
    }

    public function onReserveCfvu(Event $event): void
    {
        $data = $this->getDataFromChangeRfEvent($event);
        $context = $event->getContext();
        if ($data === null) {
            return;
        }

        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/changerf/reserver_cfvu.html.twig',
            array_merge($this->getDataChangeRf(), ['motif' => $context['motif'], 'dateCfvu' => $context['date']])
        );
        $this->myMailer->sendMessage(
            [$this->responsableDpe->getEmail()],
            '[ORéOF]  Des réserves ont été émises sur un changement de responsable de formation'
        );
    }

    public function onDeposePv(Event $event): void
    {
        $data = $this->getDataFromChangeRfEvent($event);
        if ($data === null) {
            return;
        }

        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/changerf/deposer_pv.html.twig',
            $this->getDataChangeRf()
        );
        $this->myMailer->sendMessage(
            [$this->getEmailOreof(), $this->getEmailCentral()],
            '[ORéOF]  Un PV a été déposé pour un changement de responsable de formation'
        );
    }


}
