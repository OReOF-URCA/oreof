<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/CreateUserSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 04/11/2025 15:12
 */

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\UserNotificationPreference;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostPersistEventArgs;

class CreateUserSubscriber implements EventSubscriber
{

    public function getSubscribedEvents(): array
    {
        return ['postPersist'];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof User) {
            return;
        }

        $em = $args->getObjectManager();
        $pref = new UserNotificationPreference();
        $pref->setUser($entity);
        $em->persist($pref);
        // NE PAS appeler $em->flush() ici.
    }
}
