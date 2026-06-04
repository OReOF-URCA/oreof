<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Synthese/Dto/SyntheseButtonSet.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2026 10:12
 */

declare(strict_types=1);

namespace App\Service\Synthese\Dto;

final class SyntheseButtonSet
{
    /**
     * @param list<SyntheseButton> $checks
     * @param list<SyntheseButton> $exports
     * @param list<SyntheseButton> $adminLinks
     */
    public function __construct(
        private readonly array $checks,
        private readonly array $exports,
        private readonly array $adminLinks = [],
        private readonly bool  $showExportsAsDropdown = false,
    )
    {
    }

    /** @return list<SyntheseButton> */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /** @return list<SyntheseButton> */
    public function getExports(): array
    {
        return $this->exports;
    }

    /** @return list<SyntheseButton> */
    public function getAdminLinks(): array
    {
        return $this->adminLinks;
    }

    public function shouldShowExportsAsDropdown(): bool
    {
        return $this->showExportsAsDropdown;
    }

    // Compatibilité Twig: `buttonSet.showExportsAsDropdown`
    public function isShowExportsAsDropdown(): bool
    {
        return $this->showExportsAsDropdown;
    }

    public function getShowExportsAsDropdown(): bool
    {
        return $this->showExportsAsDropdown;
    }
}
