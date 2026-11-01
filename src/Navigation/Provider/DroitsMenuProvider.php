<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/Provider/DroitsMenuProvider.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 17:35
 */


namespace App\Navigation\Provider;

use App\Navigation\MenuItem;
use App\Navigation\MenuProviderInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class DroitsMenuProvider implements MenuProviderInterface
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {}

    public function getMenu(): array
    {
        if (
            !$this->authorizationChecker->isGranted('ROLE_ADMIN')
            && !$this->authorizationChecker->isGranted('MANAGE', [
                'route' => 'app_composante',
                'subject' => 'composante',
            ])
        ) {
            return [];
        }

        return [
            MenuItem::section(
                key: 'droits',
                label: 'menu.menu_droits',
                route: 'app_section_droits',
                icon: 'mdi:shield-account-outline',
                children: [
                    MenuItem::link(
                        key: 'droits.en_attente',
                        label: 'menu.droits.en_attente',
                        route: 'app_user_profil_attente',
                        icon: 'mdi:account-clock-outline',
                    ),

                    MenuItem::link(
                        key: 'droits.affectation_profils',
                        label: 'menu.droits.affectation_profils',
                        route: 'app_user_profil_index',
                        icon: 'mdi:account-cog-outline',
                    ),

                    MenuItem::link(
                        key: 'droits.repertoire',
                        label: 'menu.droits.repertoire',
                        route: 'app_user_repertoire',
                        icon: 'mdi:contacts-outline',
                    )->requiresRole('ROLE_ADMIN'),

                    MenuItem::link(
                        key: 'droits.profils',
                        label: 'menu.droits.profils',
                        route: 'app_administration_profils_index',
                        icon: 'mdi:account-key-outline',
                    )->requiresRole('ROLE_ADMIN'),
                    MenuItem::link(
                        key: 'pilotage.impersonation',
                        label: 'menu.admin.impersonation.user',
                        route: 'app_impersonation_list',
                        icon: 'mdi:incognito',
                    )->requiresRole('ROLE_ADMIN'),
                ],
            )->withPosition(40),
        ];
    }
}
