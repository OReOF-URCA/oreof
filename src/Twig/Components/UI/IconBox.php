<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Twig/Components/UI/IconBox.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 17/05/2026 12:15
 */

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent("IconBox", template: 'components/_ui/iconBox.html.twig')]
class IconBox
{

    public ?string $icon = null;

    public ?string $color = null;

    public ?string $variant = null;

    public function getResolvedIcon(): string
    {
        if ($this->icon !== null) {
            return $this->icon;
        }

        return match ($this->variant) {
            'success' => 'icon:success',
            'danger' => 'icon:danger',
            'warning' => 'icon:warning',
            'info' => 'icon:info',
            default => 'icon:info',
        };
    }

    public function getClasses(): string
    {
        $color = $this->getResolvedColor();

        return match ($color) {
            'success' => 'bg-success-100 text-success-500',
            'danger' => 'bg-danger-100 text-red-700',
            'warning' => 'bg-warning-100 text-warning-500',
            'primary' => 'bg-primary-100 text-primary-600',
            'secondary' => 'bg-secondary-100 text-secondary-500',
            default => 'bg-secondary-100 text-secondary-500',
        };
    }

    public function getResolvedColor(): string
    {
        if ($this->color !== null) {
            return $this->color;
        }

        return match ($this->variant) {
            'success' => 'success',
            'danger' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            default => 'secondary',
        };
    }
}
