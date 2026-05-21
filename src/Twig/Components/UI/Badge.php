<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Twig/Components/UI/Badge.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/05/2026 21:05
 */

namespace App\Twig\Components\UI;

use App\DTO\BadgeView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('Badge', template: 'components/_ui/badge.html.twig')]
final class Badge
{
    public ?BadgeView $dto = null;

    /** primary | success | warning | danger | info | secondary */
    public ?string $variant = null;

    /** sm | md */
    public ?string $size = null;

    /** soft = fond teinte / solid = fond plein */
    public ?bool $soft = null;

    /** rounded-full si true */
    public ?bool $pill = null;

    /** Libelle du badge */
    public ?string $label = null;

    /** Icone optionnelle (ux_icon) */
    public ?string $icon = null;

    /** Icone a droite du libelle */
    public ?bool $iconEnd = null;

    /** Classes CSS supplementaires */
    public ?string $extraClass = null;

    #[PostMount]
    public function mount(): void
    {
        if ($this->dto === null) {
            return;
        }

        $this->label ??= $this->dto->label;
        $this->variant ??= $this->dto->variant;
        $this->size ??= $this->dto->size;
        $this->soft ??= $this->dto->soft;
        $this->pill ??= $this->dto->pill;
        $this->icon ??= $this->dto->icon;
        $this->iconEnd ??= $this->dto->iconEnd;
        $this->extraClass ??= $this->dto->extraClass;
    }
}

