<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/Breadcrumb/Attribute/Breadcrumb.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 15:50
 */

namespace App\Navigation\Breadcrumb\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Breadcrumb
{
    public function __construct(
        public string  $label = '',
        public ?string $route = null,
        public array   $routeParams = [],
        public ?string $menuKey = null,
        public ?string $parentRoute = null,
    )
    {
    }
}
