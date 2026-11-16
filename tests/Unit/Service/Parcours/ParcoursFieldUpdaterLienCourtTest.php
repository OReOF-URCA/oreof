<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Parcours;

use App\Classes\LdapImporter;
use App\Entity\Parcours;
use App\Repository\ComposanteRepository;
use App\Repository\ProfilRepository;
use App\Repository\RythmeFormationRepository;
use App\Repository\UserRepository;
use App\Repository\VilleRepository;
use App\Service\Parcours\ParcoursFieldUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ParcoursFieldUpdaterLienCourtTest extends TestCase
{
    public function testApplyLienCourtForConfigurationTab(): void
    {
        $updater = new ParcoursFieldUpdater(
            $this->createMock(UserRepository::class),
            $this->createMock(VilleRepository::class),
            $this->createMock(RythmeFormationRepository::class),
            $this->createMock(ComposanteRepository::class),
            $this->createMock(LdapImporter::class),
            $this->createMock(ProfilRepository::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $parcours = new Parcours();
        $this->assertNull($parcours->getLienCourt());

        $updater->applyForTab($parcours, 'configuration', 'parcours_step7[lienCourt]', 'https://parcoursup.fr/formation/999');
        $this->assertSame('https://parcoursup.fr/formation/999', $parcours->getLienCourt());

        $updater->applyForTab($parcours, 'configuration', 'parcours_step7[lienCourt]', '');
        $this->assertNull($parcours->getLienCourt());
    }
}
