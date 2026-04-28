<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Synthese/SyntheseButtonsProviderInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2026 10:12
 */

declare(strict_types=1);

namespace App\Service\Synthese;

use App\Entity\Parcours;
use App\Service\Synthese\Dto\SyntheseButtonSet;
use App\Service\Synthese\Dto\SyntheseButtonsContext;

interface SyntheseButtonsProviderInterface
{
    public function supports(string $typeDiplomeCode, SyntheseButtonsContext $context): bool;

    public function getButtons(Parcours $parcours, SyntheseButtonsContext $context): SyntheseButtonSet;
}

