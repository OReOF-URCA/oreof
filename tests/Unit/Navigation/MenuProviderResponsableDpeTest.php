<?php

namespace App\Tests\Unit\Navigation;

use App\Entity\Composante;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Navigation\MenuItem;
use App\Navigation\Provider\DroitsMenuProvider;
use App\Navigation\Provider\PilotageMenuProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MenuProviderResponsableDpeTest extends TestCase
{
    public function testDroitsMenuIsAvailableForResponsableDpe(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')
            ->willReturnCallback(function (string $attribute, mixed $subject = null) {
                if ($attribute === 'ROLE_ADMIN') {
                    return false;
                }
                if ($attribute === 'MANAGE' && is_array($subject) && ($subject['route'] ?? null) === 'app_composante') {
                    return true;
                }
                return false;
            });

        $provider = new DroitsMenuProvider($authorizationChecker);
        $menu = $provider->getMenu();

        $this->assertCount(1, $menu);
        $this->assertSame('droits', $menu[0]->key);
    }

    public function testPilotageMenuIncludesComposanteLinksForResponsableDpe(): void
    {
        $user = new User();
        $composante = new Composante();
        $composante->setLibelle('IUT de Troyes');
        // Reflection pour donner un ID
        $refl = new \ReflectionProperty(Composante::class, 'id');
        $refl->setValue($composante, 42);

        $user->addComposanteResponsableDpe($composante);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')
            ->willReturnCallback(function (string $attribute, mixed $subject = null) use ($composante) {
                if ($attribute === 'ROLE_ADMIN') {
                    return false;
                }
                if ($attribute === 'SHOW' && is_array($subject)) {
                    if (($subject['route'] ?? null) === 'app_etablissement') {
                        return false;
                    }
                    if (($subject['route'] ?? null) === 'app_composante' && ($subject['subject'] === 'composante' || $subject['subject'] === $composante)) {
                        return true;
                    }
                }
                return false;
            });

        $provider = new PilotageMenuProvider($security, $authorizationChecker);
        $menu = $provider->getMenu();

        $this->assertCount(1, $menu);
        /** @var MenuItem $section */
        $section = $menu[0];
        $this->assertSame('pilotage', $section->key);
        $this->assertNotEmpty($section->children);

        // Vérifie qu'on a bien les liens vers la gestion de la composante
        $keys = array_map(static fn(MenuItem $item) => $item->key, $section->children);
        $this->assertContains('pilotage_composante.42', $keys);
        $this->assertContains('pilotage_composante.42.gestion_offre', $keys);
        $this->assertContains('pilotage_composante.42.validation_dpe', $keys);
    }
}
