<?php

declare(strict_types=1);

namespace App\Tests\Unit\Navigation;

use App\Navigation\MenuItem;
use App\Navigation\Provider\PilotageMenuProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PilotageDescriptifsMenuTest extends TestCase
{
    public function testAdminPilotageMenuContainsDescriptifsParcours(): void
    {
        $security = $this->createMock(Security::class);
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $authorizationChecker->method('isGranted')
            ->willReturnCallback(function (string $attribute) {
                return $attribute === 'ROLE_ADMIN';
            });

        $provider = new PilotageMenuProvider($security, $authorizationChecker);
        $menu = $provider->getMenu();

        $this->assertCount(1, $menu);
        $pilotageSection = $menu[0];
        $this->assertSame('pilotage', $pilotageSection->key);

        $found = false;
        foreach ($pilotageSection->children as $child) {
            if ($child->key === 'pilotage.descriptifs_parcours') {
                $found = true;
                $this->assertSame('app_admin_pilotage_descriptifs_index', $child->route);
                $this->assertSame('menu.admin.descriptifs_parcours', $child->label);
                break;
            }
        }

        $this->assertTrue($found, 'Menu pilotage admin doit contenir le lien pilotage.descriptifs_parcours.');
    }
}
