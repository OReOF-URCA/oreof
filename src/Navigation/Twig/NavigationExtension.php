<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/Twig/NavigationExtension.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 15:52
 */

namespace App\Navigation\Twig;

use App\Navigation\MenuResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NavigationExtension extends AbstractExtension
{
    public function __construct(
        private readonly MenuResolver $menuResolver,
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('main_menu', [$this->menuResolver, 'mainMenu']),
            new TwigFunction('menu_item', [$this->menuResolver, 'findByKey']),
        ];
    }
}
