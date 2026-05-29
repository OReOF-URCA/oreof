<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/DTO/DotView.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 29/05/2026 12:29
 */

declare(strict_types=1);

namespace App\DTO;

/**
 * DTO pour le composant Dot (point/rond coloré minimaliste)
 */
final readonly class DotView
{
    public function __construct(
        public string  $variant = 'secondary',
        public string  $size = 'md',
        public ?string $tooltip = null,
        public ?string $extraClass = null,
    )
    {
    }
}

