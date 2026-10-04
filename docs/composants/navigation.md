# Navigation : menus, pages de section, breadcrumbs

Quand lire : ajout/modification d'une page accessible depuis la navigation, d'un menu ou d'un breadcrumb.
À mettre à jour si : `src/Navigation/` (API `MenuItem`, providers, breadcrumbs), tag `app.menu_provider`, positions des sections.

Principe : l'arbre de menus est la **source unique** de la navigation. Toute page navigable est déclarée dans un
provider → topbar, page de section et breadcrumb sont générés automatiquement. Pas de liens de menu codés en Twig.

## Architecture (`src/Navigation/`)

`Provider/*MenuProvider.php` (implémente `MenuProviderInterface::getMenu()`, tag `app.menu_provider` dans
`config/services.yaml`) → `MenuRegistry` → `MenuResolver` → topbar / pages de section / breadcrumbs.
Providers : `Main`, `Administration`, `Droits`, `Conseils`, `Pilotage`. Breadcrumbs : `Navigation/Breadcrumb/`.
Recherche : `NavigationSearchService`.

## `MenuItem`

- Fabriques : `MenuItem::section(key, label, route, icon, children)`, `MenuItem::link(key, label, route, routeParams,
  icon)`, `MenuItem::info(key, label, description, icon)`.
- Chaînables : `withChildren([...])`, `withPosition(int)`, `requiresRole('ROLE_…')`, `requires('PERM', subject)`,
  `asMegaMenu()` (sinon `MenuDisplayModeEnum::Dropdown`), `inColumn('menu.config.…')` (colonne de mega-menu et de page
  de section), `description('menu.description.…')`.

| Propriété | Règle |
|---|---|
| `key` | unique et préfixée par la section : `administration.profils` (pas `profils`) ; sert aux breadcrumbs |
| `label`, `description` | **toujours** une clé de traduction (`menu.droits.profils`), jamais un libellé en dur |
| `icon` | nom UX Icons (`mdi:account`, `icon:*`) affiché dans les pages de section |
| `position` | 10 Pilotage, 20 Conseils, 30 Droits, 90 Administration |

```php
MenuItem::section(key: 'droits', label: 'menu.menu_droits', route: 'app_section_droits', children: [
    MenuItem::link(key: 'droits.profils', label: 'menu.droits.profils', route: 'app_administration_profils_index')
        ->requiresRole('ROLE_ADMIN'),
])->withPosition(30);
```

## Droits

- `requiresRole()` sur une section → toute la section disparaît ; sur un lien → seul le lien disparaît.
- Permission métier (voter) : `->requires('EDIT', ['route' => 'app_composante', 'subject' => 'composante'])`.

## Pages de section

Chaque grand domaine a une section avec route `app_section_<key>` ; le contrôleur fait
`$menuResolver->findByKey('<key>')` et le Twig itère `menuItem.children` (regroupés par `inColumn`).

## Breadcrumbs

- Route présente dans le menu : breadcrumb automatique (Accueil → Section → Lien).
- Route absente du menu (détail, édition…) : rattacher le contrôleur avec
  `#[Breadcrumb(menuKey: 'droits.affectation_profils')]` (`App\Navigation\Breadcrumb\Attribute\Breadcrumb`, répétable ;
  paramètres `label`, `route`, `menuKey`, `parentRoute`).
- Niveau dynamique (entité) : injecter `App\Navigation\Breadcrumb\Breadcrumb` puis
  `$breadcrumb->add($formation->getDisplay(), 'formation_v2_voir', ['id' => $formation->getId()])`.
