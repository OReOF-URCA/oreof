<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/McccUpdateSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/03/2025 21:39
 */

namespace App\EventSubscriber;

use App\Events\McccUpdateEvent;
use App\Service\MutualisationChangeNotifier;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class McccUpdateSubscriber implements EventSubscriberInterface
{

    public function __construct(
        private readonly MutualisationChangeNotifier $mutualisationChangeNotifier,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            McccUpdateEvent::UPDATE_MCCC => 'onMcccUpdate',
        ];
    }

    public function onMcccUpdate(McccUpdateEvent $event): void
    {
        // Les notifications sont gérées par DoctrineMutualisationUpdateSubscriber
        // qui surveille les entités Mccc et ElementConstitutif lors du flush.
        // Ce subscriber est conservé pour usage futur (ex: journalisation des diffs).
    }
}
