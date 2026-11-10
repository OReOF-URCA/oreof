<?php

namespace App\Navigation\Provider;

use App\Navigation\MenuItem;
use App\Navigation\MenuProviderInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class ConseilsMenuProvider implements MenuProviderInterface
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    )
    {
    }

    public function getMenu(): array
    {
        if (
            !$this->authorizationChecker->isGranted('ROLE_ADMIN')
            && !$this->authorizationChecker->isGranted('EDIT', [
                'route' => 'app_etablissement',
                'subject' => 'etablissement',
            ])
        ) {
            return [];
        }

        return [
            MenuItem::section(
                key: 'conseils',
                label: 'menu.menu_conseils',
                route: 'app_section_conseils',
                icon: 'mdi:scale-balance',
                children: [
                    MenuItem::link(
                        key: 'conseils.chgt_rf',
                        label: 'menu.conseils.chgt_rf',
                        route: 'app_formation_responsable_liste',
                        icon: 'mdi:account-switch-outline',
                    )->withDescription('menu.conseils.description.chgt_rf'),

                    MenuItem::link(
                        key: 'conseils.synthese_modifs',
                        label: 'menu.conseils.synthses_modifs',
                        route: 'app_synthese_modification_export_pdf',
                        icon: 'mdi:file-compare',
                    )->withDescription('menu.conseils.description.synthese_modifs'),

                    MenuItem::link(
                        key: 'conseils.synthese_offre',
                        label: 'menu.conseils.synthese_offre',
                        route: 'app_conseils_synthese_offre',
                        icon: 'mdi:file-chart-outline',
                    )->withDescription('menu.conseils.description.synthese_offre'),
                ],
            )->withPosition(30),
        ];
    }
}

