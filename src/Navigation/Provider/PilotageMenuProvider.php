<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/Provider/PilotageMenuProvider.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 17:05
 */


namespace App\Navigation\Provider;

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
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return [$this->adminPilotageMenu()];
        }

        if (!$this->authorizationChecker->isGranted('SHOW', [
            'route' => 'app_composante',
            'subject' => 'composante',
        ])) {
            return [];
        }

        return [$this->composantePilotageMenu()];
    }

    private function adminPilotageMenu(): MenuItem
    {
        return MenuItem::section(
            key: 'pilotage',
            label: 'menu.admin.pilotage',
            route: 'app_section_pilotage',
            icon: 'chart-bar',
            children: [
                MenuItem::link(
                    'pilotage.consulter_offre',
                    'menu.admin.consulter_offre',
                    'structure_composante_index',
                ),

                MenuItem::link(
                    'pilotage.gestion_offre',
                    'menu.admin.gestion_offre',
                    'app_offre',
                ),

                MenuItem::link(
                    'pilotage.dpe_ouverts',
                    'menu.admin.dpe_ouverts',
                    'app_demande_dpe',
                ),

                MenuItem::link(
                    'pilotage.validation_dpe',
                    'menu.admin.validation_dpe',
                    'app_validation_dpe_index',
                ),

                MenuItem::link(
                    'pilotage.validation_fiches_ec',
                    'menu.admin.validation_fiches_ec',
                    'app_validation_fiche_index',
                ),

                MenuItem::link(
                    'pilotage.validation_rf',
                    'menu.admin.validation_rf',
                    'app_validation_change_rf_index',
                ),

                MenuItem::link(
                    'pilotage.controle_lheo',
                    'menu.admin.controle_lheo',
                    'app_parcours_lheo_invalid_list',
                ),

                MenuItem::link(
                    'pilotage.exports',
                    'menu.admin.exports',
                    'app_export_index',
                ),

                MenuItem::link(
                    'pilotage.logs_applicatifs',
                    'menu.admin.logs.applicatifs',
                    'app_log_viewer_index',
                ),

                MenuItem::link(
                    'pilotage.impersonation',
                    'menu.admin.impersonation.user',
                    'app_impersonation_list',
                ),
            ],
        )->withPosition(20);
    }

    private function composantePilotageMenu(): MenuItem
    {
        $user = $this->security->getUser();

        if ($user === null || !method_exists($user, 'getComposanteResponsableDpe')) {
            return MenuItem::section(
                key: 'pilotage_composante',
                label: 'menu.compo.pilotage',
                icon: 'chart-bar',
                children: [],
            );
        }

        $children = [];

        foreach ($user->getComposanteResponsableDpe() as $composante) {
            if (!$this->authorizationChecker->isGranted('SHOW', [
                'route' => 'app_composante',
                'subject' => $composante,
            ])) {
                continue;
            }

            $column = $composante->getLibelle();

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.consulter_offre', $composante->getId()),
                label: 'menu.compo.consulter_offre',
                route: 'structure_composante_index',
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.dpe_ouvert', $composante->getId()),
                label: 'menu.compo.dpe_ouvert',
                route: 'app_demande_dpe_composante',
                routeParams: ['composante' => $composante->getId()],
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.validation_dpe', $composante->getId()),
                label: 'menu.compo.validation_dpe',
                route: 'app_validation_composante_dpe_index',
                routeParams: ['composante' => $composante->getId()],
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.validation_ec', $composante->getId()),
                label: 'menu.compo.validation_ec',
                route: 'app_validation_composante_fiche_index',
                routeParams: ['composante' => $composante->getId()],
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.validation_rf', $composante->getId()),
                label: 'menu.compo.validation_rf',
                route: 'app_validation_composante_change_rf_index',
                routeParams: ['composante' => $composante->getId()],
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.pilotage_visuel', $composante->getId()),
                label: 'menu.compo.pilotage_visuel',
                route: 'app_validation_composante_pilotage',
                routeParams: ['composante' => $composante->getId()],
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.exports', $composante->getId()),
                label: 'menu.compo.exports',
                route: 'app_export_composante_index',
                routeParams: ['composante' => $composante->getId()],
            )->inColumn($column);

            $children[] = MenuItem::link(
                key: sprintf('pilotage_composante.%s.communication', $composante->getId()),
                label: 'menu.compo.communication',
                route: 'app_plaquette',
                routeParams: ['composante' => $composante->getId()],
            )->inColumn($column);
        }

        $children[] = MenuItem::info(
            key: 'pilotage_composante.support',
            label: 'Aide & Support',
            description: 'Accédez à tous les guides administratifs et aux contacts de la scolarité centrale.',
        )->inColumn('support');

        return MenuItem::section(
            key: 'pilotage_composante',
            label: 'menu.compo.pilotage',
            route: 'app_section_pilotage',
            icon: 'chart-bar',
            children: $children,
        )->asMegaMenu()->withPosition(20);
    }
}
