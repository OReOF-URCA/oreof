<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/DpeWorkflow/WorkflowDpeMailSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 06/10/2023 08:31
 */

namespace App\EventSubscriber\DpeWorkflow;

use App\Classes\GetHistorique;
use App\Classes\Mailer;
use App\Repository\ComposanteRepository;
use App\Repository\FormationRepository;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

/** @deprecated */
class DpeComposanteMailSubscriber extends AbstractDpeMailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected GetHistorique        $getHistorique,
        protected UserRepository       $userRepository,
        protected ComposanteRepository $composanteRepository,
        protected FormationRepository  $formationRepository,
        protected Mailer               $myMailer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
//           // 'workflow.dpeParcours.transition.valider_rf' => 'onValideRf',
//            //todo: ajouter valider DPE pour prévenir RF et RP?
//            'workflow.dpeParcours.transition.refuser_dpe_composante' => 'onRefuseDpeComposante',
//            'workflow.dpeParcours.transition.reserver_dpe_composante' => 'onReserveDpeComposante',
//            'workflow.dpeParcours.transition.refuser_conseil' => 'onRefuseConseil',
//            'workflow.dpeParcours.transition.valider_conseil' => 'onValideConseil', //todo: ajouter un état pour prévenir RF et RP?
//            'workflow.dpeParcours.transition.reserver_conseil' => 'onReserveConseil',
//            'workflow.dpe.transition.valider_ouverture_sans_cfvu' => 'onTransmetSesSansCfvu',

        ];
    }

    public function onRefuseConseil(Event $event): void
    {
        //mail au RF
        $data = $this->getDataFromEvent($event);
        if ($data === null) {
            return;
        }
        $context = $event->getContext();

        //todo: check si le responsable de formation accepte le mail
        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/formation/refuse_conseil_composante.html.twig',
            array_merge($this->getData(), ['motif' => $context['motif']])
        );

        $titre = $this->hasParcours ?
            'La fiche du parcours ' . $this->parcours->getLibelle() . ' de la formation ' . $this->formation->getDisplay() . ' a été refusée par le conseil de votre composante' :
            'La fiche de la formation ' . $this->formation->getDisplay() . ' a été refusée par le conseil de votre composante';

        $this->myMailer->sendMessage(
            $this->getDestinataires(),
            '[ORéOF]  '.$titre
        );
    }

    public function onValideConseil(Event $event): void
    {
        $data = $this->getDataFromEvent($event);
        if ($data === null) {
            return;
        }
        //todo: check si le responsable de formation accepte le mail

        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/formation/valide_conseil_composante.html.twig',
            array_merge($this->getData(),
            [
                'composante' => $this->formation->getComposantePorteuse(),
                'historique' => $this->getHistorique->getHistoriqueParcoursLastStep($this->dpeParcours, 'soumis_conseil')
            ])
        );

        $titre = $this->hasParcours ?
            'La fiche du parcours ' . $this->parcours->getLibelle() . ' de la formation ' . $this->formation->getDisplay() . ' a été validée par le conseil de la composante' :
            'La fiche de la formation ' . $this->formation->getDisplay() . ' a été validée par le conseil de la composante';

        $this->myMailer->sendMessage(
            [$this->getEmailCentral(), $this->getEmailOreof()],
            '[ORéOF]  '.$titre
        );
    }
    public function onTransmetSesSansCfvu(Event $event): void
    {
        $data = $this->getDataFromEvent($event);
        if ($data === null) {
            return;
        }
        //todo: check si le responsable de formation accepte le mail

        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/formation/valide_conseil_sans_cfvu.html.twig',
            array_merge($this->getData(),
            [
                'composante' => $this->formation->getComposantePorteuse(),
            ])
        );

        $titre = $this->hasParcours ?
            'Parcours ' . $this->parcours->getLibelle().' de la formation '.$this->formation->getDisplay(). ' a été modifié sans passage en CFVU' :
            'Formation ' . $this->formation->getDisplay(). ' a été modifiée sans passage en CFVU';

        $this->myMailer->sendMessage(
            [$this->getEmailCentral(), $this->getEmailOreof()],
            '[ORéOF]  '.$titre
        );
    }

    public function onReserveDpeComposante(Event $event): void
    {
        $data = $this->getDataFromEvent($event);
        if ($data === null) {
            return;
        }
        $context = $event->getContext();

        //todo: check si le responsable de formation accepte le mail
        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/formation/reserve_dpe_composante.html.twig',
            array_merge($this->getData(), ['motif' => $context['motif']])
        );

        $titre = $this->hasParcours ?
            'La fiche du parcours ' . $this->parcours->getLibelle() . ' de la formation ' . $this->formation->getDisplay() . ' a reçu des réserves de la part du DPE de votre composante' :
            'La fiche de la formation ' . $this->formation->getDisplay() . ' a reçu des réserves de la part du DPE de votre composante';

        $this->myMailer->sendMessage(
            $this->getDestinataires(),
            '[ORéOF]  '.$titre
        );
    }

    public function onReserveConseil(Event $event): void
    {
        //mail au RF
        $data = $this->getDataFromEvent($event);
        if ($data === null) {
            return;
        }
        $context = $event->getContext();

        //todo: check si le responsable de formation accepte le mail
        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/formation/reserve_conseil_composante.html.twig',
            array_merge($this->getData(), ['motif' => $context['motif']])
        );

        $titre = $this->hasParcours ?
            'La fiche du parcours ' . $this->parcours->getLibelle() . ' de la formation ' . $this->formation->getDisplay() . ' a reçu des réserves du conseil de votre composante' :
            'La fiche de la formation ' . $this->formation->getDisplay() . ' a reçu des réserves du conseil de votre composante';

        $this->myMailer->sendMessage(
            $this->getDestinataires(),
            '[ORéOF] '.$titre
        );
    }

    public function onRefuseDpeComposante(Event $event): void
    {
        //mail au RF
        $data = $this->getDataFromEvent($event);
        if ($data === null) {
            return;
        }
        $context = $event->getContext();

        //todo: check si le responsable de formation accepte le mail
        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/formation/refuse_dpe_composante.html.twig',
            array_merge($this->getData(), ['motif' => $context['motif']])
        );

        $titre = $this->hasParcours ?
            'La fiche du parcours ' . $this->parcours->getLibelle() . ' de la formation ' . $this->formation->getDisplay() . ' a été refusée par le DPE de votre composante' :
            'La fiche de la formation ' . $this->formation->getDisplay() . ' a été refusée par le DPE de votre composante';

        $this->myMailer->sendMessage(
            $this->getDestinataires(),
            '[ORéOF]  '.$titre
        );
    }

    public function onValideRf(Event $event): void
    {
        $data = $this->getDataFromEvent($event);
        if ($data === null) {
            return;
        }

        //todo: check si le responsable de formation accepte le mail


        $this->myMailer->initEmail();
        $this->myMailer->setTemplate(
            'mails/workflow/formation/valide_rf.html.twig',
            $this->getData()
        );

        $titre = $this->hasParcours ?
            'Le parcours ' . $this->parcours->getLibelle().' de la formation '.$this->formation->getDisplay(). ' a été validé par son responsable' :
            'La formation ' . $this->formation->getDisplay(). ' a été validée par son responsable';

        $this->myMailer->sendMessage(
            [$this->responsableDpe->getEmail()], //on ajoute le RP?
            '[ORéOF]  '.$titre
        );
    }


}
