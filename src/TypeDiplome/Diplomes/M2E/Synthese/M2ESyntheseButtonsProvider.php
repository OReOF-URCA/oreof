<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/M2E/Synthese/M2ESyntheseButtonsProvider.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2026 10:25
 */

declare(strict_types=1);

namespace App\TypeDiplome\Diplomes\M2E\Synthese;

use App\Entity\Parcours;
use App\Service\Synthese\Dto\SyntheseButton;
use App\Service\Synthese\Dto\SyntheseButtonSet;
use App\Service\Synthese\Dto\SyntheseButtonsContext;
use App\Service\Synthese\SyntheseButtonsProviderInterface;

final class M2ESyntheseButtonsProvider implements SyntheseButtonsProviderInterface
{
    public function supports(string $typeDiplomeCode, SyntheseButtonsContext $context): bool
    {
        return $typeDiplomeCode === 'M2E' && !$context->isVersion();
    }

    public function getButtons(Parcours $parcours, SyntheseButtonsContext $context): SyntheseButtonSet
    {
        $id = $parcours->getId();

        $checks = [
            new SyntheseButton('Contrôler les BCC', 'app_parcours_bcc', ['parcours' => $id], 'btn btn-outline-primary d-block', 'fas fa-check'),
            new SyntheseButton('Contrôler la maquette', 'app_parcours_ec', ['parcours' => $id], 'btn btn-outline-primary d-block mt-1', 'fas fa-check'),
            new SyntheseButton('Coeff. / UE / EC', 'app_parcours_ec_m2e_coeff', ['parcours' => $id], 'btn btn-outline-primary d-block mt-1', 'fas fa-check'),
        ];

        $exports = [
            new SyntheseButton('Export MCCC (xlsx)', 'app_parcours_mccc_export', ['parcours' => $id, '_format' => 'xlsx'], 'btn btn-outline-primary d-block mt-1'),
        ];

        return new SyntheseButtonSet($checks, $exports);
    }
}

