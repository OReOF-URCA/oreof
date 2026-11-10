<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/Breadcrumb/Breadcrumb.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 15:49
 */

namespace App\Navigation\Breadcrumb;

final class Breadcrumb
{
    private array $items = [];

    public function add(?string $label, ?string $route = null, array $routeParams = []): self
    {
        $cleanLabel = $label !== null ? trim($label) : '';
        if ($cleanLabel === '') {
            return $this;
        }

        $this->items[] = new BreadcrumbItem($cleanLabel, $route, $routeParams);

        return $this;
    }

    public function items(): array
    {
        return $this->items;
    }
}
