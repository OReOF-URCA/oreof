<?php

namespace App\Twig;

use App\Entity\CampagneCollecte;
use App\Enums\CampagneModuleEnum;
use App\Service\CampagneAccessibilityService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CampagneAccessibilityExtension extends AbstractExtension
{
    public function __construct(
        private readonly CampagneAccessibilityService $accessibilityService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_module_open', [$this, 'isModuleOpen']),
            new TwigFunction('can_edit_module', [$this, 'canEditModule']),
            new TwigFunction('module_status', [$this, 'getModuleStatus']),
        ];
    }

    public function isModuleOpen(string|CampagneModuleEnum $module, ?CampagneCollecte $campagne = null): bool
    {
        return $this->accessibilityService->isModuleOpen($module, $campagne);
    }

    public function canEditModule(string|CampagneModuleEnum $module, ?CampagneCollecte $campagne = null): bool
    {
        return $this->accessibilityService->canUserEditModule($module, $campagne);
    }

    public function getModuleStatus(string|CampagneModuleEnum $module, ?CampagneCollecte $campagne = null): array
    {
        return $this->accessibilityService->getModuleStatus($module, $campagne);
    }
}
