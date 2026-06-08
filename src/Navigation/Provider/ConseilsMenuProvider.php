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
                icon: 'scale',
                children: [
                    MenuItem::link(
                        key: 'conseils.chgt_rf',
                        label: 'menu.conseils.chgt_rf',
                        route: 'app_formation_responsable_liste',
                    ),

                    MenuItem::link(
                        key: 'conseils.synthese_modifs',
                        label: 'menu.conseils.synthses_modifs',
                        route: 'app_synthese_modification_export_pdf',
                    ),

                    MenuItem::link(
                        key: 'conseils.documents',
                        label: 'menu.conseils.documents',
                        route: 'app_conseils_documents_index',
                    ),
                ],
            )->withPosition(30),
        ];
    }
}
