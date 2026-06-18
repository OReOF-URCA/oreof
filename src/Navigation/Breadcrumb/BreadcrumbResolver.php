<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/Breadcrumb/BreadcrumbResolver.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 15:49
 */

namespace App\Navigation\Breadcrumb;

use App\Navigation\Breadcrumb\Attribute\Breadcrumb as BreadcrumbAttribute;
use App\Navigation\MenuResolver;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class BreadcrumbResolver
{
    public function __construct(
        private RequestStack $requestStack,
        private MenuResolver $menuResolver,
    )
    {
    }

    public function resolve(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return [];
        }

        $route = $request->attributes->get('_route');
        $controller = $request->attributes->get('_controller');

        $items = [
            new BreadcrumbItem('breadcrumb.home', 'app_homepage', [], 'icon:home'),
        ];

        $menuPath = $this->menuResolver->findPathByRoute($route);

        foreach ($menuPath as $menuItem) {
            $items[] = new BreadcrumbItem(
                label: $menuItem->label,
                route: $menuItem->route,
                routeParams: $menuItem->routeParams,
            );
        }

        foreach ($this->getAttributes($controller) as $attribute) {
            if ($attribute->menuKey !== null) {
                $menuItem = $this->menuResolver->findByKey($attribute->menuKey);

                if ($menuItem !== null) {
                    $menuPath = $this->menuResolver->findPathByRoute($menuItem->route);

                    foreach ($menuPath as $item) {
                        $items[] = new BreadcrumbItem(
                            label: $item->label,
                            route: $item->route,
                            routeParams: $item->routeParams,
                        );
                    }
                }

                continue;
            }

            $items[] = new BreadcrumbItem(
                label: $attribute->label,
                route: $attribute->route,
                routeParams: $attribute->routeParams,
            );
        }

        return $this->deduplicate($items);
    }

    private function getAttributes(string $controller): array
    {
        if (!str_contains($controller, '::')) {
            return [];
        }

        [$class, $method] = explode('::', $controller);

        $reflectionMethod = new \ReflectionMethod($class, $method);

        return array_map(
            static fn(\ReflectionAttribute $attribute) => $attribute->newInstance(),
            $reflectionMethod->getAttributes(BreadcrumbAttribute::class)
        );
    }

    private function deduplicate(array $items): array
    {
        $result = [];
        $seen = [];

        foreach ($items as $item) {
            $key = $item->route . ':' . json_encode($item->routeParams, JSON_THROW_ON_ERROR);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
    }
}
