<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/DTO/BadgeView.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 21/05/2026 15:19
 */

declare(strict_types=1);

namespace App\DTO;

final readonly class BadgeView
{
    public function __construct(
        public string $label,
        public string $variant = 'secondary',
        public string $size = 'sm',
        public bool   $soft = true,
        public bool   $pill = true,
        public string $icon = '',
        public bool   $iconEnd = false,
        public string $extraClass = '',
    )
    {
    }
}

