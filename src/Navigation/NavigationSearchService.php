<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/NavigationSearchService.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 08/06/2026 09:49
 */

namespace App\Navigation;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NavigationSearchService
{
    public function __construct(
        private MenuResolver          $menuResolver,
        private UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function search(string $query): array
    {
        $items = $this->flatten(
            $this->menuResolver->mainMenu()
        );

        return array_values(
            array_filter(
                $items,
                fn(array $item) => str_contains(
                    mb_strtolower($item['search']),
                    mb_strtolower($query)
                )
            )
        );
    }

    private function flatten(
        array $items,
        array $parents = []
    ): array
    {
        $result = [];

        foreach ($items as $item) {
            $path = [...$parents, $item->label];

            if ($item->route) {
                $result[] = [
                    'key' => $item->key,
                    'label' => $item->label,
                    'route' => $item->route,
                    'routeParams' => $item->routeParams,
                    'path' => implode(' > ', $path),
                    'search' => implode(' ', $path),
                    'icon' => $item->icon,
                    'url' => $this->urlGenerator->generate(
                        $item->route,
                        $item->routeParams
                    ),
                ];
            }

            $result = [
                ...$result,
                ...$this->flatten(
                    $item->children,
                    $path
                ),
            ];
        }

        return $result;
    }
}
