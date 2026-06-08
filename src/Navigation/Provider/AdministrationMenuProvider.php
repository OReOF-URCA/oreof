<?php

namespace App\Navigation\Provider;

use App\Navigation\MenuItem;
use App\Navigation\MenuProviderInterface;

final readonly class AdministrationMenuProvider implements MenuProviderInterface
{
    public function getMenu(): array
    {
        return [
            MenuItem::section(
                key: 'administration',
                label: 'menu.menu_administration',
                route: 'app_section_administration',
                icon: 'settings',
                children: [
                    MenuItem::link(
                        key: 'administration.etablissement',
                        label: 'menu.config.etablissement',
                        route: 'app_etablissement_index',
                    )->inColumn('menu.config.menu_etablissement'),

                    MenuItem::link(
                        key: 'administration.composante',
                        label: 'menu.config.composante',
                        route: 'app_composante_index',
                    )->inColumn('menu.config.menu_etablissement'),

                    MenuItem::link(
                        key: 'administration.ville',
                        label: 'menu.config.ville',
                        route: 'app_ville_index',
                    )->inColumn('menu.config.menu_etablissement'),

                    MenuItem::link(
                        key: 'administration.domaines',
                        label: 'menu.config.domaines',
                        route: 'app_domaine_index',
                    )->inColumn('menu.config.offre_formation'),

                    MenuItem::link(
                        key: 'administration.mentions',
                        label: 'menu.config.mentions',
                        route: 'app_mention_index',
                    )->inColumn('menu.config.offre_formation'),

                    MenuItem::link(
                        key: 'administration.type_diplome',
                        label: 'menu.config.type_diplome',
                        route: 'app_type_diplome_index',
                    )->inColumn('menu.config.offre_formation'),

                    MenuItem::link(
                        key: 'administration.type_ue',
                        label: 'menu.config.type_ue',
                        route: 'app_type_ue_index',
                    )->inColumn('menu.config.offre_formation'),

                    MenuItem::link(
                        key: 'administration.type_ec',
                        label: 'menu.config.type_ec',
                        route: 'app_type_ec_index',
                    )->inColumn('menu.config.offre_formation'),

                    MenuItem::link(
                        key: 'administration.nature_ue',
                        label: 'menu.config.nature_ue',
                        route: 'app_nature_ue_ec_index',
                    )->inColumn('menu.config.offre_formation'),

                    MenuItem::link(
                        key: 'administration.type_epreuve',
                        label: 'menu.config.type_epreuve',
                        route: 'app_type_epreuve_index',
                    )->inColumn('menu.config.offre_formation'),

                    MenuItem::link(
                        key: 'administration.langues',
                        label: 'menu.config.langues',
                        route: 'app_langue_index',
                    )->inColumn('menu.config.offre_formation'),

                    MenuItem::link(
                        key: 'administration.rythme_formation',
                        label: 'menu.config.rythme_formation',
                        route: 'app_rythme_formation_index',
                    )->inColumn('menu.config.offre_formation'),

                    MenuItem::link(
                        key: 'administration.campagne_collecte',
                        label: 'menu.config.campagne_collecte',
                        route: 'app_campagne_collecte_index',
                    )->inColumn('menu.menu_configuration'),

                    MenuItem::link(
                        key: 'administration.annee_universitaire',
                        label: 'menu.config.annee_universitaire',
                        route: 'app_annee_universitaire_index',
                    )->inColumn('menu.menu_configuration'),

                    MenuItem::link(
                        key: 'administration.actualites',
                        label: 'menu.config.actualites',
                        route: 'app_actualite_index',
                    )->inColumn('menu.menu_configuration'),

                    MenuItem::link(
                        key: 'administration.notifications',
                        label: 'menu.config.notifications',
                        route: 'app_notication_liste',
                    )->inColumn('menu.menu_configuration'),

                    MenuItem::link(
                        key: 'administration.traductions',
                        label: 'menu.config.traductions',
                        route: 'translations_index',
                    )->inColumn('menu.menu_configuration'),

                    MenuItem::link(
                        key: 'administration.config_emails',
                        label: 'menu.config.config_emails',
                        route: 'app_config_email',
                    )->inColumn('menu.menu_configuration'),

                    MenuItem::link(
                        key: 'administration.help',
                        label: 'menu.config.help',
                        route: 'app_help_index',
                    )->inColumn('menu.menu_configuration'),

                    MenuItem::link(
                        key: 'administration.faq',
                        label: 'menu.config.faq',
                        route: 'app_faq_index',
                    )->inColumn('menu.menu_configuration'),

                    MenuItem::link(
                        key: 'administration.styleguide',
                        label: 'menu.config.styleguide',
                        route: 'admin_styleguide_index',
                    )->inColumn('menu.menu_configuration'),

                    MenuItem::info(
                        key: 'administration.support',
                        label: 'Aide & Support',
                        description: 'Accédez à tous les guides administratifs et aux contacts de la scolarité centrale.',
                    )->inColumn('support'),
                ],
            )
                ->requiresRole('ROLE_ADMIN')
                ->asMegaMenu()
                ->withPosition(100),
        ];
    }
}
