<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\CampagneModuleEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Service\CampagneAccessibilityService;
use App\Service\Parcours\ParcoursAccessExplainer;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Workflow\WorkflowInterface;

class ParcoursAccessExplainerTest extends TestCase
{
    public function testExplainsWhenParcoursIsFerme(): void
    {
        $campagneService = $this->createMock(CampagneAccessibilityService::class);
        $campagneService->method('getModuleStatus')->willReturn(['canEdit' => true, 'message' => 'Ouvert']);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $explainer = new ParcoursAccessExplainer($campagneService, $security);

        $formation = new Formation(null);
        $parcours = new Parcours($formation);

        $dpe = new DpeParcours();
        $dpe->setParcours($parcours);
        $dpe->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE);
        $parcours->getDpeParcours()->add($dpe);

        $reason = $explainer->explainEditDenial($parcours);

        $this->assertStringContainsString('fermé pour cette campagne', $reason);
        $this->assertStringContainsString('Non ouverture', $reason);
    }

    public function testExplainsWorkflowStepLock(): void
    {
        $campagneService = $this->createMock(CampagneAccessibilityService::class);
        $campagneService->method('getModuleStatus')->willReturn(['canEdit' => true, 'message' => 'Ouvert']);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $explainer = new ParcoursAccessExplainer($campagneService, $security);

        $formation = new Formation(null);
        $parcours = new Parcours($formation);

        $dpe = new DpeParcours();
        $dpe->setParcours($parcours);
        $dpe->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION);
        $dpe->setEtatValidation(['soumis_conseil' => 1]);
        $parcours->getDpeParcours()->add($dpe);

        $reason = $explainer->explainEditDenial($parcours);

        $this->assertStringContainsString('soumis au conseil', $reason);
    }
}
