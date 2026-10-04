<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Twig/Components/UI/AlerteComponent.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 03/05/2026 08:10
 */

namespace App\Twig\Components\UI;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('alerte', template: 'components/_ui/alerte.html.twig')]
final class AlerteComponent extends AbstractController
{
    public string $type = 'info';
    public string $message = '';

    public function getIcone(): string
    {
        return match ($this->type) {
            'info' => 'icon:info:bold',
            'help' => 'icon:question:bold',
            'success' => 'icon:success:bold',
            'warning' => 'icon:warning:bold',
            'danger' => 'icon:danger:bold',
            default => 'icon:info:bold',
        };
    }
}
