# Navigation : menus, pages de section, breadcrumbs

Quand lire : ajout/modification d'une page accessible depuis la navigation, d'un menu ou d'un breadcrumb.
À mettre à jour si : `src/Navigation/` (API `MenuItem`, providers, breadcrumbs), `templates/_layout/_topbar.html.twig`, `templates/_layout/menu/`, tag `app.menu_provider`, positions des sections, `templates/_layout/_breadcrumb.html.twig`, composants `Breadcrumb` (PHP/Twig).

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

### Rendu Twig : kit shadcn adapté

`templates/_layout/_breadcrumb.html.twig` consomme toujours `breadcrumbs()` : le resolver, les attributs des
contrôleurs, les paramètres de route et les traductions du domaine `menu` restent la source des éléments.
Le fil est masqué s’il ne contient que l’accueil.

Le rendu utilise les composants `Breadcrumb`, `Breadcrumb:List`, `Breadcrumb:Item`, `Breadcrumb:Link`,
`Breadcrumb:Page` et `Breadcrumb:Separator`, avec classes PHP dans `src/Twig/Components/UI/` et templates dans
`templates/components/Breadcrumb.html.twig` et `templates/components/Breadcrumb/`.

Source : [installation manuelle du kit Symfony UX Toolkit shadcn](https://ux.symfony.com/toolkit/kits/shadcn/components/breadcrumb).
Les templates sont copiés et adaptés au projet, pas installés comme bibliothèque : structure composée et
`data-slot` du kit, palette sémantique ORéOF, séparateur `/`, mode sombre `data-theme` et variantes sticky conservés.
Les classes sont fournies via `attributes.defaults()` (fusion native des classes Twig Component) ; pas de filtre
`tailwind_classes`, de CSS global shadcn ni de dépendance JS/animation pour ce composant statique.
Les mises à jour du kit doivent être comparées et reportées manuellement. Éviter les utilitaires contradictoires
lorsqu’on ajoute une classe à un composant ; modifier son template pour changer ses valeurs par défaut.

Accessibilité : navigation nommée, séparateurs décoratifs masqués, liens avec focus visible ; seule la dernière
entrée porte `aria-current="page"`. Une entrée intermédiaire sans route reste du texte, sans état de page courante.
Le composant `Page` utilise un `span` natif sans rôle de lien désactivé.

Vérification visuelle : contrôler une page avec plusieurs niveaux (dont un niveau sans route), un libellé long
sur mobile, puis les thèmes clair/sombre et l’en-tête avant/après passage en sticky. Parcourir les liens au clavier.
