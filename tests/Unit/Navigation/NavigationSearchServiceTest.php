<?php

namespace App\Tests\Unit\Navigation;

use App\Navigation\MenuItem;
use App\Navigation\MenuResolver;
use App\Navigation\NavigationSearchService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class NavigationSearchServiceTest extends TestCase
{
    public function testSearchReturnsTranslatedLabelsAndHandlesAccents(): void
    {
        $authorizationChecker = $this->createMock(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $translator->method('trans')
            ->willReturnCallback(function (string $id, array $parameters = [], ?string $domain = null) {
                return match ($id) {
                    'menu.offre_formation' => 'Offre de formation',
                    'menu.detail_mentions' => 'Détail des formations',
                    'menu.config.etablissement' => 'Établissement',
                    default => $id,
                };
            });

        $urlGenerator->method('generate')
            ->willReturnCallback(fn(string $route) => '/' . $route);

        $menu = [
            MenuItem::section(
                key: 'offre',
                label: 'menu.offre_formation',
                route: null,
                children: [
                    MenuItem::link(
                        key: 'offre.detail_mentions',
                        label: 'menu.detail_mentions',
                        route: 'app_formation_index'
                    ),
                ]
            ),
            MenuItem::link(
                key: 'etablissement',
                label: 'menu.config.etablissement',
                route: 'app_etablissement_index'
            ),
        ];

        $provider = new class($menu) implements \App\Navigation\MenuProviderInterface {
            public function __construct(private array $menu) {}
            public function getMenu(): array { return $this->menu; }
        };

        $menuRegistry = new \App\Navigation\MenuRegistry([$provider]);
        $menuResolver = new MenuResolver($menuRegistry, $authorizationChecker);

        $service = new NavigationSearchService($menuResolver, $urlGenerator, $translator);

        // Search with accents
        $results = $service->search('Détail');
        $this->assertCount(1, $results);
        $this->assertSame('Détail des formations', $results[0]['label']);
        $this->assertSame('Offre de formation > Détail des formations', $results[0]['path']);
        $this->assertSame('/app_formation_index', $results[0]['url']);

        // Search without accents
        $resultsNoAccent = $service->search('etablissement');
        $this->assertCount(1, $resultsNoAccent);
        $this->assertSame('Établissement', $resultsNoAccent[0]['label']);

        // Search in parent section name
        $resultsSection = $service->search('formation');
        $this->assertCount(1, $resultsSection);
        $this->assertSame('Détail des formations', $resultsSection[0]['label']);
    }
}
