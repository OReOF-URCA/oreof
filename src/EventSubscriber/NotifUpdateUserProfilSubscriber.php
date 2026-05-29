<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/NotifUpdateUserProfilSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 30/05/2025 16:06
 */

namespace App\EventSubscriber;

use App\Classes\Mailer;
use App\Events\NotifUpdateUserProfilEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NotifUpdateUserProfilSubscriber implements EventSubscriberInterface
{

    public function __construct(private Mailer $mailer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NotifUpdateUserProfilEvent::UPDATE_USER_PROFIL => 'onUpdateUserProfil',
            NotifUpdateUserProfilEvent::ADD_USER_PROFIL => 'onAddUserProfil',
            NotifUpdateUserProfilEvent::DELETE_USER_PROFIL => 'onDeleteUserProfil',
        ];
    }

    public function onUpdateUserProfil(NotifUpdateUserProfilEvent $event): void
    {
        $user = $event->userProfil->getUser();

        if ($user === null) {
            return;
        }

        $this->mailer->initEmail();
        $this->mailer->setTemplate(
            'mails/user_profil/update.txt.twig',
            ['userProfil' => $event->userProfil]
        );

        $this->mailer->sendMessage([$user->getEmail()], '[ORéOF] Un de vos droits a été mis à jour');
    }

    public function onAddUserProfil(NotifUpdateUserProfilEvent $event): void
    {
        $user = $event->userProfil->getUser();

        if ($user === null) {
            return;
        }

        $this->mailer->initEmail();
        $this->mailer->setTemplate(
            'mails/user_profil/add.txt.twig',
            ['userProfil' => $event->userProfil]
        );

        $this->mailer->sendMessage([$user->getEmail()], '[ORéOF] Des droits ont été ajoutés');
    }

    public function onDeleteUserProfil(NotifUpdateUserProfilEvent $event): void
    {
        $user = $event->userProfil->getUser();

        if ($user === null) {
            return;
        }

        $this->mailer->initEmail();
        $this->mailer->setTemplate(
            'mails/user_profil/delete.txt.twig',
            ['userProfil' => $event->userProfil]
        );

        $this->mailer->sendMessage([$user->getEmail()], '[ORéOF] Un de vos droits a été supprimé');
    }

}
