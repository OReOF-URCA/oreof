<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/AbstractNotifCentreSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/06/2025 16:45
 */

namespace App\EventSubscriber;

use App\Classes\Mailer;
use App\Entity\Composante;
use App\Entity\Etablissement;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Profil;
use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractNotifCentreSubscriber implements EventSubscriberInterface
{
    public function __construct(protected Mailer $mailer)
    {
    }

    protected function sendNotification(
        User|UserInterface                          $user,
        Etablissement|Composante|Formation|Parcours $entity,
        Profil                                      $profil,
        string                                      $template,
        string                                      $subject): void
    {
        $this->mailer->initEmail();
        $this->mailer->setTemplate($template, [
            'user' => $user,
            'entity' => $entity,
            'profil' => $profil,
        ]);
        $this->mailer->sendMessage([$user->getEmail()], $subject);
    }
}
