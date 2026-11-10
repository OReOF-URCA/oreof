<?php

declare(strict_types=1);

namespace App\Tests\Unit\Navigation;

use App\Navigation\Provider\ConseilsMenuProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ConseilsMenuProviderTest extends TestCase
{
    public function testMenuIsEmptyWhenNotGranted(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')
            ->willReturn(false);

        $provider = new ConseilsMenuProvider($authorizationChecker);
        $menu = $provider->getMenu();

        $this->assertEmpty($menu);
    }

    public function testMenuHasExactlyThreeEntriesWhenGranted(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')
            ->willReturnCallback(function (string $attribute, mixed $subject = null): bool {
                return $attribute === 'ROLE_ADMIN';
            });

        $provider = new ConseilsMenuProvider($authorizationChecker);
        $menu = $provider->getMenu();

        $this->assertCount(1, $menu);
        $section = $menu[0];
        $this->assertSame('conseils', $section->key);
        $this->assertSame('menu.menu_conseils', $section->label);
        $this->assertSame('app_section_conseils', $section->route);

        $children = $section->children;
        $this->assertCount(3, $children);

        $this->assertSame('conseils.chgt_rf', $children[0]->key);
        $this->assertSame('app_formation_responsable_liste', $children[0]->route);

        $this->assertSame('conseils.synthese_modifs', $children[1]->key);
        $this->assertSame('app_synthese_modification_export_pdf', $children[1]->route);

        $this->assertSame('conseils.synthese_offre', $children[2]->key);
        $this->assertSame('app_conseils_synthese_offre', $children[2]->route);
    }
}
