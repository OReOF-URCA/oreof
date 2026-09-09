<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/Provider/MainMenuProvider.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 15:51
 */

namespace App\Navigation\Provider;

use App\Navigation\MenuItem;
use App\Navigation\MenuProviderInterface;

final class MainMenuProvider implements MenuProviderInterface
{
    public function getMenu(): array
    {
        return [
            MenuItem::section(
                key: 'offre',
                label: 'menu.offre_formation',
                route: 'app_section_offre_formation',
                icon: 'book-open',
                children: [
                    MenuItem::link(
                        key: 'offre.detail_mentions',
                        label: 'menu.detail_mentions',
                        route: 'app_formation_index',
                        icon: 'mdi:format-list-bulleted'
                    ),
                    MenuItem::link(
                        key: 'offre.detail_fiches',
                        label: 'menu.detail_fiches',
                        route: 'structure_fiche_matiere_index',
                        icon: 'mdi:file'
                    ),
                    MenuItem::link(
                        key: 'offre.detail_fiches_hd',
                        label: 'menu.detail_fiches_hd',
                        route: 'structure_fiche_matiere_index_hd',
                        icon: 'mdi:file-lock'
                    ),
                    MenuItem::link(
                        key: 'offre.exports',
                        label: 'menu.exports',
                        route: 'app_export_index',
                        icon: 'icon:download'
                    ),
                ]
            )->withPosition(10),
        ];
    }
}
