<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Synthese/DefaultSyntheseButtonsProvider.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2026 10:12
 */

declare(strict_types=1);

namespace App\Service\Synthese;

use App\Entity\Parcours;
use App\Service\Synthese\Dto\SyntheseButton;
use App\Service\Synthese\Dto\SyntheseButtonSet;
use App\Service\Synthese\Dto\SyntheseButtonsContext;

final class DefaultSyntheseButtonsProvider implements SyntheseButtonsProviderInterface
{
    public function supports(string $typeDiplomeCode, SyntheseButtonsContext $context): bool
    {
        return true;
    }

    public function getButtons(Parcours $parcours, SyntheseButtonsContext $context): SyntheseButtonSet
    {
        $id = $parcours->getId();

        $checks = [
            new SyntheseButton('Contrôler les BCC', 'app_parcours_bcc', ['parcours' => $id], 'btn btn-outline-primary d-block', 'fas fa-check'),
            new SyntheseButton('Contrôler la maquette', 'app_parcours_ec', ['parcours' => $id], 'btn btn-outline-primary d-block mt-1', 'fas fa-check'),
        ];

        if (!$context->isVersion()) {
            $exports = [
                new SyntheseButton('Export MCCC (xlsx)', 'app_parcours_mccc_export', ['parcours' => $id, '_format' => 'xlsx'], 'btn btn-outline-primary d-block mt-1'),
                new SyntheseButton('Export simplifié des MCCC (xlsx)', 'app_parcours_mccc_export_light', ['parcours' => $id, '_format' => 'xlsx'], 'btn btn-outline-primary d-block mt-1'),
            ];

            return new SyntheseButtonSet($checks, $exports);
        }
        if ($context->isNewParcoursForCampaign()) {
            $exports = [
                new SyntheseButton('Export MCCC simplifié (pdf)', 'app_parcours_mccc_export_cfvu_valid', ['parcours' => $id, 'format' => 'simplifie']),
                new SyntheseButton('Export MCCC (xlsx)', 'app_parcours_mccc_export', ['parcours' => $id, '_format' => 'xlsx']),
            ];
        } else {
            $exports = [
                new SyntheseButton('Export MCCC simplifié (pdf)', 'app_parcours_mccc_export_cfvu_valid', ['parcours' => $id, 'format' => 'simplifie']),
                new SyntheseButton('Export MCCC (xlsx)', 'app_parcours_mccc_export', ['parcours' => $id, '_format' => 'xlsx']),
                new SyntheseButton('Export MCCC Version (xlsx)', 'app_parcours_mccc_export_versionning', ['parcours' => $id, '_format' => 'xlsx'], 'btn btn-warning d-block mt-1'),];
        }

        return new SyntheseButtonSet($checks, $exports);
    }
}
