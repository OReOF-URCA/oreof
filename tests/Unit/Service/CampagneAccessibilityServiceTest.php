<?php

namespace App\Tests\Unit\Service;

use App\Classes\DataUserSession;
use App\Entity\CampagneCollecte;
use App\Entity\TimelineDate;
use App\Enums\CampagneModuleEnum;
use App\Repository\CampagneCollecteRepository;
use App\Service\CampagneAccessibilityService;
use DateTime;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CampagneAccessibilityServiceTest extends TestCase
{
    public function testIsModuleOpenReturnsTrueWhenDateIsWithinActiveStep(): void
    {
        $dataUserSession = $this->createMock(DataUserSession::class);
        $security = $this->createMock(Security::class);
        $repository = $this->createMock(CampagneCollecteRepository::class);

        $campagne = new CampagneCollecte();

        $step1 = new TimelineDate();
        $step1->setLibelle('Saisie de l\'offre');
        $step1->setDateDebut(new DateTime('2026-09-01'));
        $step1->setDate(new DateTime('2026-09-30'));
        $step1->setModulesActifs([CampagneModuleEnum::OFFRE_FORMATION]);

        $step2 = new TimelineDate();
        $step2->setLibelle('Descriptifs');
        $step2->setDateDebut(new DateTime('2026-10-01'));
        $step2->setDate(new DateTime('2026-10-31'));
        $step2->setModulesActifs([CampagneModuleEnum::DESCRIPTIFS]);

        $campagne->addTimelineDate($step1);
        $campagne->addTimelineDate($step2);

        $dataUserSession->method('getCampagneCollecte')->willReturn($campagne);

        $service = new CampagneAccessibilityService($dataUserSession, $security, $repository);

        $testDate = new DateTime('2026-09-15');
        $this->assertTrue($service->isModuleOpen(CampagneModuleEnum::OFFRE_FORMATION, $campagne, $testDate));
        $this->assertFalse($service->isModuleOpen(CampagneModuleEnum::DESCRIPTIFS, $campagne, $testDate));
        $this->assertFalse($service->isModuleOpen(CampagneModuleEnum::MAQUETTES, $campagne, $testDate));

        $testDateOctober = new DateTime('2026-10-15');
        $this->assertFalse($service->isModuleOpen(CampagneModuleEnum::OFFRE_FORMATION, $campagne, $testDateOctober));
        $this->assertTrue($service->isModuleOpen(CampagneModuleEnum::DESCRIPTIFS, $campagne, $testDateOctober));
    }

    public function testAdminBypassAllowsEditingEvenWhenClosed(): void
    {
        $dataUserSession = $this->createMock(DataUserSession::class);
        $security = $this->createMock(Security::class);
        $repository = $this->createMock(CampagneCollecteRepository::class);

        $campagne = new CampagneCollecte();
        $dataUserSession->method('getCampagneCollecte')->willReturn($campagne);

        $service = new CampagneAccessibilityService($dataUserSession, $security, $repository);

        // Standard user (not admin) -> false
        $security->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);
        $this->assertFalse($service->canUserEditModule(CampagneModuleEnum::OFFRE_FORMATION, $campagne));

        // Admin user -> true
        $securityAdmin = $this->createMock(Security::class);
        $securityAdmin->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);
        $serviceAdmin = new CampagneAccessibilityService($dataUserSession, $securityAdmin, $repository);
        $this->assertTrue($serviceAdmin->canUserEditModule(CampagneModuleEnum::OFFRE_FORMATION, $campagne));
    }

    public function testGetModuleStatusReturnsAccurateMessages(): void
    {
        $dataUserSession = $this->createMock(DataUserSession::class);
        $security = $this->createMock(Security::class);
        $repository = $this->createMock(CampagneCollecteRepository::class);

        $campagne = new CampagneCollecte();

        $step = new TimelineDate();
        $step->setLibelle('Ouverture Offre');
        $step->setDateDebut(new DateTime('2026-11-01'));
        $step->setDate(new DateTime('2026-11-30'));
        $step->setModulesActifs([CampagneModuleEnum::OFFRE_FORMATION]);
        $campagne->addTimelineDate($step);

        $dataUserSession->method('getCampagneCollecte')->willReturn($campagne);
        $service = new CampagneAccessibilityService($dataUserSession, $security, $repository);

        $statusBefore = $service->getModuleStatus(CampagneModuleEnum::OFFRE_FORMATION, $campagne, new DateTime('2026-10-15'));
        $this->assertFalse($statusBefore['isOpen']);
        $this->assertStringContainsString('Ouverture prévue le 01/11/2026', $statusBefore['message']);

        $statusDuring = $service->getModuleStatus(CampagneModuleEnum::OFFRE_FORMATION, $campagne, new DateTime('2026-11-15'));
        $this->assertTrue($statusDuring['isOpen']);
        $this->assertStringContainsString('ouvert jusqu\'au 30/11/2026', $statusDuring['message']);

        $statusAfter = $service->getModuleStatus(CampagneModuleEnum::OFFRE_FORMATION, $campagne, new DateTime('2026-12-05'));
        $this->assertFalse($statusAfter['isOpen']);
        $this->assertStringContainsString('Période de saisie terminée', $statusAfter['message']);
    }
}
