# Navigation : menus, pages de section, breadcrumbs

Quand lire : ajout/modification d'une page accessible depuis la navigation, d'un menu ou d'un breadcrumb.
À mettre à jour si : `src/Navigation/` (API `MenuItem`, providers, breadcrumbs), `templates/_layout/_topbar.html.twig`, `templates/_layout/menu/`, tag `app.menu_provider`, positions des sections.

Principe : l'arbre de menus est la **source unique** de la navigation. Toute page navigable est déclarée dans un
provider → topbar, page de section et breadcrumb sont générés automatiquement. Pas de liens de menu codés en Twig.

## Architecture (`src/Navigation/`)

`Provider/*MenuProvider.php` (implémente `MenuProviderInterface::getMenu()`, tag `app.menu_provider` dans
`config/services.yaml`) → `MenuRegistry` → `MenuResolver` → topbar / pages de section / breadcrumbs.
Providers : `Main`, `Administration`, `Droits`, `Conseils`, `Pilotage`. Breadcrumbs : `Navigation/Breadcrumb/`.
Recherche : `NavigationSearchService`.

## Topbar (`templates/_layout/_topbar.html.twig`)

- < `md` : menu masqué ; `md` → `2xl` : deux lignes (logo + actions / menu) ; ≥ `2xl` : une ligne. En texte « Grande »
  (accessibilité), deux lignes jusqu'à 120rem (règles hors `@layer` dans `app.css`). « Accès rapide » ≥ 1800px.
- Liens de premier niveau : classe `topbar-link` (libellé sur une ligne). Hauteur publiée dans `--topbar-height`.
- Mega-menu : `grid-cols-{{ columns|length }}` → classes `grid-cols-1..8` en safelist (`@source inline` dans `app.css`).

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
| colonnes Administration | `menu.config.menu_etablissement`, `menu_config_globale`, `offre_formation`, `menu.menu_configuration`, `menu.config.menu_developpement` (outils techniques : stats de visites, logs, versioning JSON, styleguide, doc API) |

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

## Accessibilité de la navigation principale

Les dropdowns et méga-menus utilisent un modèle de dépliage de liens, sans rôles ARIA `menu`/`menuitem` :
Tab parcourt les liens normalement. Le lien de section reste navigable ; un bouton distinct ouvre ses sous-liens
(Entrée/Espace natifs), avec `aria-controls` et `aria-expanded`. Sans route de section, le bouton porte aussi le libellé.

`navbar-dropdown` synchronise visibilité et état accessible : survol à la souris, ouverture au focus sur les liens,
maintien tant que le focus est dans le groupe, fermeture en quittant le groupe ou au clic extérieur ; Échap ferme
et rend le focus au bouton. Le survol ignore les pointeurs tactiles pour ne pas inverser la première activation.
Les menus compte et campagne utilisent le même contrôleur et possèdent aussi un déclencheur identifié.

`base.html.twig` propose en premier lien « Aller au contenu », visible au focus, vers `main#main-content`
avec `tabindex="-1"`. La marge de défilement tient compte de l’en-tête sticky via `--page-header-offset`.

Le rendu reste dans `templates/_layout/_breadcrumb.html.twig`, sans dépendance à shadcn. Seule la dernière
entrée porte `aria-current="page"` ; les séparateurs sont masqués aux technologies d’assistance et les liens
possèdent un focus visible.
