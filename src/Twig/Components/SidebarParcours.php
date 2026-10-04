<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/Components/SidebarParcours.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/01/2026 20:33
 */

namespace App\Twig\Components;

use App\Entity\Parcours;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent()]
class SidebarParcours
{
    use DefaultActionTrait;

    #[LiveProp]
    public Parcours $parcours;

    // Cette fonction sera rappelée par l'événement 'item-updated'
    #[LiveListener('item-updated')]
    public function refresh(): void
    { /* Refresh automatique */
    }
}
