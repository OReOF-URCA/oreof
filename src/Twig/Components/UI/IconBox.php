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
            'emerald' => 'bg-emerald-100 text-emerald-700',
            'red' => 'bg-red-100 text-red-700',
            'amber' => 'bg-amber-100 text-amber-700',
            'blue' => 'bg-blue-100 text-blue-700',
            'slate' => 'bg-slate-100 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function getResolvedColor(): string
    {
        if ($this->color !== null) {
            return $this->color;
        }

        return match ($this->variant) {
            'success' => 'emerald',
            'danger' => 'red',
            'warning' => 'amber',
            'info' => 'blue',
            default => 'slate',
        };
    }
}
