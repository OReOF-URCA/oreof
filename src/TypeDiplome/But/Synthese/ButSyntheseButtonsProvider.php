<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/But/Synthese/ButSyntheseButtonsProvider.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2026 10:25
 */

declare(strict_types=1);

namespace App\TypeDiplome\But\Synthese;

use App\Entity\Parcours;
use App\Service\Synthese\SyntheseButtonsProviderInterface;
use App\Service\Synthese\Dto\SyntheseButton;
use App\Service\Synthese\Dto\SyntheseButtonSet;
use App\Service\Synthese\Dto\SyntheseButtonsContext;

final class ButSyntheseButtonsProvider implements SyntheseButtonsProviderInterface
{
    public function supports(string $typeDiplomeCode, SyntheseButtonsContext $context): bool
    {
        return $typeDiplomeCode === 'BUT';
    }

    public function getButtons(Parcours $parcours, SyntheseButtonsContext $context): SyntheseButtonSet
    {
        $id = $parcours->getId();

        $checks = [
            new SyntheseButton('Contrôler les AC/Compétences', 'app_parcours_bcc_but', ['parcours' => $id], 'btn btn-outline-primary d-block', 'fas fa-check'),
            new SyntheseButton('Contrôler les Ressources/SAE', 'app_parcours_ressources_sae_but', ['parcours' => $id], 'btn btn-outline-primary d-block mt-1', 'fas fa-check'),
            new SyntheseButton('Coeff. / UE / Ressources/SAE', 'app_parcours_ressources_sae_but_coeff', ['parcours' => $id], 'btn btn-outline-primary d-block mt-1', 'fas fa-check'),
        ];

        if (!$context->isVersion()) {
            $exports = [
                new SyntheseButton('Export MCCC (xlsx)', 'app_parcours_mccc_export', ['parcours' => $id, '_format' => 'xlsx'], 'btn btn-outline-primary d-block mt-1'),
            ];

            return new SyntheseButtonSet($checks, $exports);
        }

        $exports = [
            new SyntheseButton('Export MCCC simplifié (pdf)', 'app_parcours_mccc_export_cfvu_valid', ['parcours' => $id, 'format' => 'simplifie']),
            new SyntheseButton('Export MCCC (xlsx)', 'app_parcours_mccc_export', ['parcours' => $id, '_format' => 'xlsx']),
            new SyntheseButton('Export MCCC Version (xlsx)', 'app_parcours_mccc_export_versionning', ['parcours' => $id, '_format' => 'xlsx']),
        ];

        $adminLinks = [];
        if ($context->isAdmin()) {
            $adminLinks[] = new SyntheseButton('Correction BUT', 'app_but_correction', ['parcours' => $id]);
        }

        if ($context->isPublishedOrValidToPublish()) {
            return new SyntheseButtonSet($checks, $exports, $adminLinks, true);
        }

        $exports = [
            new SyntheseButton('Export MCCC (xlsx)', 'app_parcours_mccc_export', ['parcours' => $id, '_format' => 'xlsx'], 'btn btn-outline-info d-block mt-1'),
            new SyntheseButton('Export MCCC Version (xlsx)', 'app_parcours_mccc_export_versionning', ['parcours' => $id, '_format' => 'xlsx'], 'btn btn-warning d-block mt-1'),
        ];

        return new SyntheseButtonSet($checks, $exports, $adminLinks, false);
    }
}


