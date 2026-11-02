<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/Provider/PilotageMenuProvider.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 17:05
 */


namespace App\Navigation\Provider;

use App\Entity\User;
use App\Navigation\MenuDisplayModeEnum;
use App\Navigation\MenuDisplayModeEnum as MenuDisplayMode;
use App\Navigation\MenuItem;
use App\Navigation\MenuProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class PilotageMenuProvider implements MenuProviderInterface
{
    public function __construct(
        private Security                      $security,
        private AuthorizationCheckerInterface $authorizationChecker,
    )
    {
    }

    public function getMenu(): array
    {
        if (
            $this->authorizationChecker->isGranted('ROLE_ADMIN')
            || $this->authorizationChecker->isGranted('SHOW', [
                'route' => 'app_etablissement',
                'subject' => 'etablissement',
            ])
        ) {
            return [$this->adminPilotageMenu()];
        }

        if ($this->authorizationChecker->isGranted('SHOW', [
            'route' => 'app_composante',
            'subject' => 'composante',
        ])) {
            return [$this->composantePilotageMenu()];
        }

        return [];
    }

    private function adminPilotageMenu(): MenuItem
    {
        return MenuItem::section(
            key: 'pilotage',
            label: 'menu.admin.pilotage',
            route: 'app_section_pilotage',
            icon: 'mdi:chart-bar',
            children: [
                MenuItem::link(
                    'pilotage.tour_de_controle',
                    'menu.admin.tour_de_controle',
                    'app_admin_pilotage_campagne_index',
                    icon: 'mdi:monitor-dashboard',
                ),

                MenuItem::link(
                    'pilotage.consulter_offre',
                    'menu.admin.consulter_offre',
                    'structure_composante_index',
                    icon: 'mdi:book-search-outline',
                ),

                MenuItem::link(
                    'pilotage.gestion_offre',
                    'menu.admin.gestion_offre',
                    'app_offre_index',
                    icon: 'mdi:notebook-edit-outline',
                ),

                MenuItem::link(
                    'pilotage.statistiques_offre',
                    'menu.admin.statistiques_offre',
                    'offre_v2_statistiques',
                    icon: 'icon:chart-pie',
                ),

                MenuItem::link(
                    'pilotage.dpe_ouverts',
                    'menu.admin.dpe_ouverts',
                    'app_demande_dpe',
                    icon: 'mdi:folder-open-outline',
                ),

                MenuItem::link(
                    'pilotage.validation_dpe',
                    'menu.admin.validation_dpe',
                    'app_validation_dpe_index',
                    icon: 'mdi:folder-check-outline',
                ),

                MenuItem::link(
                    'pilotage.validation_fiches_ec',
                    'menu.admin.validation_fiches_ec',
                    'app_validation_fiche_index',
                    icon: 'mdi:file-check-outline',
                ),

                MenuItem::link(
                    'pilotage.validation_rf',
                    'menu.admin.validation_rf',
                    'app_validation_change_rf_index',
                    icon: 'mdi:account-check-outline',
                ),

                MenuItem::link(
                    'pilotage.controle_lheo',
                    'menu.admin.controle_lheo',
                    'app_parcours_lheo_invalid_list',
                    icon: 'mdi:shield-check-outline',
                ),

                MenuItem::link(
                    'pilotage.exports',
                    'menu.admin.exports',
                    'app_export_index',
                    icon: 'icon:download',
                )->requires('SHOW', [
                    'route' => 'app_etablissement',
                    'subject' => 'etablissement',
                ]),
            ],
        )->withPosition(20);
    }

    private function composantePilotageMenu(): MenuItem
    {
        $user = $this->security->getUser();

        if ($user === null || !method_exists($user, 'getComposanteResponsableDpe')) {
            return MenuItem::section(
                key: 'pilotage',
                label: 'menu.compo.pilotage',
                icon: 'mdi:chart-bar',
                children: [],
            );
        }

        $children = [];

        $composantes = [];
        foreach ($user->getComposanteResponsableDpe() as $composante) {
            $composantes[$composante->getId()] = $composante;
        }
        if (method_exists($user, 'getUserProfils')) {
            foreach ($user->getUserProfils() as $userProfil) {
                if ($userProfil->getComposante() !== null) {
                    $composantes[$userProfil->getComposante()->getId()] = $userProfil->getComposante();
                }
            }
        }

        foreach ($composantes as $composante) {
            if (!$this->authorizationChecker->isGranted('SHOW', [
                'route' => 'app_composante',
                'subject' => $composante,
            ])) {
                continue;
            }

            $column = $composante->getLibelle();

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s', $composante->getId()),
                label: 'menu.compo.gestion_composante',
                route: 'app_composante',
                routeParams: ['composante' => $composante->getId()],
                icon: 'mdi:domain',
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.gestion_offre', $composante->getId()),
                label: 'menu.compo.gestion_offre',
                route: 'app_offre_composante_index',
                routeParams: ['composante' => $composante->getId()],
                icon: 'mdi:notebook-edit-outline',
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.dpe_ouvert', $composante->getId()),
                label: 'menu.compo.dpe_ouvert',
                route: 'app_demande_dpe_composante',
                routeParams: ['composante' => $composante->getId()],
                icon: 'mdi:folder-open-outline',
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.validation_dpe', $composante->getId()),
                label: 'menu.compo.validation_dpe',
                route: 'app_validation_composante_dpe_index',
                routeParams: ['composante' => $composante->getId()],
                icon: 'mdi:folder-check-outline',
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.validation_ec', $composante->getId()),
                label: 'menu.compo.validation_ec',
                route: 'app_validation_composante_fiche_index',
                routeParams: ['composante' => $composante->getId()],
                icon: 'mdi:file-check-outline',
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.validation_rf', $composante->getId()),
                label: 'menu.compo.validation_rf',
                route: 'app_validation_composante_change_rf_index',
                routeParams: ['composante' => $composante->getId()],
                icon: 'mdi:account-check-outline',
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.pilotage_visuel', $composante->getId()),
                label: 'menu.compo.pilotage_visuel',
                route: 'app_validation_composante_pilotage',
                routeParams: ['composante' => $composante->getId()],
                icon: 'mdi:view-dashboard-outline',
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.exports', $composante->getId()),
                label: 'menu.compo.exports',
                route: 'app_export_composante_index',
                routeParams: ['composante' => $composante->getId()],
                icon: 'icon:download',
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.communication', $composante->getId()),
                label: 'menu.compo.communication',
                route: 'app_plaquette',
                routeParams: ['composante' => $composante->getId()],
                icon: 'mdi:bullhorn-outline',
            )->inColumn($column);
        }

        $children[] = MenuItem::info(
            key: 'pilotage_composante.support',
            label: 'Aide & Support',
            description: 'Accédez à tous les guides administratifs et aux contacts de la scolarité centrale.',
            icon: 'mdi:lifebuoy',
        )->inColumn('support');

        return MenuItem::section(
            key: 'pilotage',
            label: 'menu.compo.pilotage',
            route: 'app_section_pilotage',
            icon: 'mdi:chart-bar',
            children: $children,
        )->asMegaMenu()->withPosition(20);
    }
}
