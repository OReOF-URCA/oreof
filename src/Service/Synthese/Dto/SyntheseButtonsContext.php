<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Synthese/Dto/SyntheseButtonsContext.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2026 10:12
 */

declare(strict_types=1);

namespace App\Service\Synthese\Dto;

final class SyntheseButtonsContext
{
    public function __construct(
        private readonly bool $version,
        private readonly bool $admin,
        private readonly bool $publishedOrValidToPublish,
        private readonly bool $isNewParcoursForCampaign,
    )
    {
    }

    public function isVersion(): bool
    {
        return $this->version;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function isPublishedOrValidToPublish(): bool
    {
        return $this->publishedOrValidToPublish;
    }

    public function isNewParcoursForCampaign()
    {
        return $this->isNewParcoursForCampaign;
    }
}

