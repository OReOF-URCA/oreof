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
use Symfony\Contracts\Translation\TranslatorInterface;

final class NavigationSearchService
{
    public function __construct(
        private MenuResolver          $menuResolver,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface   $translator,
    )
    {
    }

    public function search(string $query): array
    {
        $normalizedQuery = $this->normalize($query);

        if ($normalizedQuery === '') {
            return [];
        }

        $items = $this->flatten(
            $this->menuResolver->mainMenu()
        );

        return array_values(
            array_filter(
                $items,
                fn(array $item) => str_contains(
                    $item['search'],
                    $normalizedQuery
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
            $translatedLabel = $this->translator->trans($item->label, [], 'menu');
            $path = [...$parents, $translatedLabel];

            if ($item->route) {
                $translatedDescription = $item->description ? $this->translator->trans($item->description, [], 'menu') : '';

                $searchTokens = [
                    ...$path,
                    $translatedDescription,
                    $item->key,
                    $item->route,
                ];

                $result[] = [
                    'key' => $item->key,
                    'label' => $translatedLabel,
                    'route' => $item->route,
                    'routeParams' => $item->routeParams,
                    'path' => implode(' > ', $path),
                    'search' => $this->normalize(implode(' ', array_filter($searchTokens))),
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

    private function normalize(string $text): string
    {
        $normalized = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);

        return $normalized !== false ? trim($normalized) : trim(mb_strtolower($text));
    }
}
