<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/Breadcrumb/BreadcrumbItem.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 15:49
 */

namespace App\Navigation\Breadcrumb;

final readonly class BreadcrumbItem
{
    public function __construct(
        public string  $label,
        public ?string $route = null,
        public array   $routeParams = [],
        public ?string $icon = null
    )
    {
    }
}
