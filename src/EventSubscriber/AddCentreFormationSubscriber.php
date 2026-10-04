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
use App\Events\AddCentreFormationEvent;
use App\Events\NotifCentreFormationEvent;
use App\Repository\FormationRepository;
use App\Repository\UserProfilRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AddCentreFormationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected Mailer               $mailer,
        protected FormationRepository  $formationRepository,
        protected UserProfilRepository          $userProfilRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AddCentreFormationEvent::ADD_CENTRE_FORMATION => 'onAddCentreFormation',
            AddCentreFormationEvent::REMOVE_CENTRE_FORMATION => 'onRemoveCentreFormation',
        ];
    }

    public function onAddCentreFormation(AddCentreFormationEvent $event): void
    {
        $user = $event->user;
        $formation = $event->formation;
        $campagneCollecte = $event->campagneCollecte;
        $profil = $event->droits;

        // on vérifie s'il est déjà dans un centre
        $existe = $this->userProfilRepository->findOneBy(['user' => $user, 'formation' => $formation]);
        // si oui, on vérifie s'il est déjà dans la formation

        if ($existe !== null) {
            //S'il existe on met à jour les droits
            if ($event->droits !== $existe->getProfil()?->getCode()) {
                // on récupère le profil correspondant
                $existe->setProfil($profil);
                $this->entityManager->flush();
                $eventNotif = new NotifCentreFormationEvent($formation, $user, $profil);
                $this->eventDispatcher->dispatch($eventNotif, NotifCentreFormationEvent::NOTIF_UPDATE_CENTRE);
            }
        } else {
            // on ajoute le centre à la formation
            $centre = new UserProfil();
            $centre->setUser($user);
            $centre->setFormation($formation);
            $centre->setCampagneCollecte($campagneCollecte);
            $centre->setProfil($profil);
            $this->entityManager->persist($centre);
            $this->entityManager->flush();
            $eventNotif = new NotifCentreFormationEvent($formation, $user, $profil);
            $this->eventDispatcher->dispatch($eventNotif, NotifCentreFormationEvent::NOTIF_ADD_CENTRE);
        }

        $this->entityManager->flush();
    }

    public function onRemoveCentreFormation(AddCentreFormationEvent $event): void
    {
        $user = $event->user;
        $formation = $event->formation;

        //on vérifie s'il est déjà dans le centre
        $existe = $this->userProfilRepository->findOneBy(['user' => $user, 'formation' => $formation]);

        //si oui, on supprime le centre
        if ($existe !== null) {
            $profil = $existe->getProfil();

            $this->entityManager->remove($existe);
            $this->entityManager->flush();

            $eventNotif = new NotifCentreFormationEvent($formation, $user, $profil);
            $this->eventDispatcher->dispatch($eventNotif, NotifCentreFormationEvent::NOTIF_REMOVE_CENTRE);
        }
    }
}
