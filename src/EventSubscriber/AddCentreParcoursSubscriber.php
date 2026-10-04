<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/UserAccesSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\EventSubscriber;

use App\Classes\Mailer;
use App\Entity\UserProfil;
use App\Events\AddCentreParcoursEvent;
use App\Events\NotifCentreFormationEvent;
use App\Events\NotifCentreParcoursEvent;
use App\Repository\ParcoursRepository;
use App\Repository\UserProfilRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AddCentreParcoursSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected EventDispatcherInterface    $eventDispatcher,
        protected Mailer                      $mailer,
        protected ParcoursRepository $parcoursRepository,
        protected EntityManagerInterface      $entityManager,
        private readonly UserProfilRepository $userProfilRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AddCentreParcoursEvent::ADD_CENTRE_PARCOURS => 'onAddCentreParcours',
            AddCentreParcoursEvent::REMOVE_CENTRE_PARCOURS => 'onRemoveCentreParcours',
        ];
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function onAddCentreParcours(AddCentreParcoursEvent $event): void
    {
        //todo: réécrire pour refactoriser avec la création de centre
        $user = $event->user ?? $event->parcours->getRespParcours();
        $parcours = $event->parcours;
        $campagneCollecte = $event->campagneCollecte;
        $profil = $event->droits;

        // on vérifie s'il est déjà dans un centre
        $existe = $this->userProfilRepository->findOneBy(['user' => $user, 'parcours' => $parcours]);
        // si oui, on vérifie s'il est déjà dans la formation

        if ($existe !== null) {
            //S'il existe on met à jour les droits
            if ($event->droits !== $existe->getProfil()?->getCode()) {
                // on récupère le profil correspondant
                $existe->setProfil($profil);
                $this->entityManager->flush();
                $eventNotif = new NotifCentreParcoursEvent($parcours, $user, $profil);
                $this->eventDispatcher->dispatch($eventNotif, NotifCentreParcoursEvent::NOTIF_UPDATE_CENTRE);
            }
        } else {
            // on ajoute le centre à la formation
            $centre = new UserProfil();
            $centre->setUser($user);
            $centre->setParcours($parcours);
            $centre->setCampagneCollecte($campagneCollecte);
            $centre->setProfil($profil);
            $this->entityManager->persist($centre);
            $this->entityManager->flush();

            $eventNotif = new NotifCentreParcoursEvent($parcours, $user, $profil);
            $this->eventDispatcher->dispatch($eventNotif, NotifCentreParcoursEvent::NOTIF_ADD_CENTRE);
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function onRemoveCentreParcours(
        AddCentreParcoursEvent $event): void
    {
        $user = $event->user;
        $parcours = $event->parcours;

        //on vérifie s'il est déjà dans le centre
        $existe = $this->userProfilRepository->findOneBy(['user' => $user, 'parcours' => $parcours]);

        //si oui, on supprime le centre
        if ($existe !== null) {
            $profil = $existe->getProfil();

            $this->entityManager->remove($existe);
            $this->entityManager->flush();

            $eventNotif = new NotifCentreParcoursEvent($parcours, $user, $profil);
            $this->eventDispatcher->dispatch($eventNotif, NotifCentreParcoursEvent::NOTIF_REMOVE_CENTRE);
        }
    }
}
