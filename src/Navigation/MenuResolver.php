<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/MenuResolver.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 15:49
 */

namespace App\Navigation;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class MenuResolver
{
    public function __construct(
        private MenuRegistry                  $registry,
        private AuthorizationCheckerInterface $authorizationChecker,
    )
    {
    }

    public function findByKey(string $key): ?MenuItem
    {
        foreach ($this->mainMenu() as $item) {
            $found = $this->findRecursive($item, $key);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    public function mainMenu(): array
    {
        return $this->filter($this->registry->all());
    }

    private function filter(array $items): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (!$this->isVisible($item)) {
                continue;
            }

            $children = $this->filter($item->children);

            if ($item->route === null && $children === []) {
                continue;
            }

            $visible[] = $item->withChildren($children);
        }

        usort(
            $visible,
            static fn(MenuItem $a, MenuItem $b) => $a->position <=> $b->position
        );

        return $visible;
    }

    private function isVisible(MenuItem $item): bool
    {
        if ($item->role !== null) {
            return $this->authorizationChecker->isGranted($item->role);
        }

        if ($item->permission !== null) {
            return $this->authorizationChecker->isGranted($item->permission, $item->subject);
        }

        return true;
    }

    private function findRecursive(MenuItem $item, string $key): ?MenuItem
    {
        if ($item->key === $key) {
            return $item;
        }

        foreach ($item->children as $child) {
            $found = $this->findRecursive($child, $key);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    public function findPathByRoute(string $route): array
    {
        foreach ($this->mainMenu() as $item) {
            $path = $this->findPathRecursive($item, $route);

            if ($path !== null) {
                return $path;
            }
        }

        return [];
    }

    private function findPathRecursive(MenuItem $item, string $route, array $parents = []): ?array
    {
        $currentPath = [...$parents, $item];

        if ($item->route === $route) {
            return $currentPath;
        }

        foreach ($item->children as $child) {
            $found = $this->findPathRecursive($child, $route, $currentPath);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
