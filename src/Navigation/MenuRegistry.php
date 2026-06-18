<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/MenuRegistry.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 15:49
 */

namespace App\Navigation;

final readonly class MenuRegistry
{
    /**
     * @param iterable<MenuProviderInterface> $providers
     */
    public function __construct(
        private iterable $providers,
    )
    {
    }

    public function all(): array
    {
        $items = [];

        foreach ($this->providers as $provider) {
            $items = [
                ...$items,
                ...$provider->getMenu(),
            ];
        }

        return $items;
    }
}
