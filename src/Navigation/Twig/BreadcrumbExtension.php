<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/Twig/BreadcrumbExtension.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 16:04
 */

namespace App\Navigation\Twig;

use App\Navigation\Breadcrumb\BreadcrumbResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BreadcrumbExtension extends AbstractExtension
{
    public function __construct(
        private readonly BreadcrumbResolver $breadcrumbResolver,
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('breadcrumbs', [$this->breadcrumbResolver, 'resolve']),
        ];
    }
}
