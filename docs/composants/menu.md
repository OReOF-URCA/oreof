# Navigation : Menus et Breadcrumbs

## Objectif

Le système de navigation centralise la définition des menus de l'application afin de :

* générer automatiquement la barre de navigation ;
* générer les pages de section (pages d'orientation) ;
* générer automatiquement les breadcrumbs ;
* appliquer les droits d'accès de manière homogène ;
* éviter la duplication des liens dans les templates Twig.

L'arbre de navigation devient ainsi la source unique de vérité de la navigation de l'application.

---

# Architecture

## Structure générale

```text
Provider(s)
    ↓
MenuRegistry
    ↓
MenuResolver
    ↓
Topbar
Pages de section
Breadcrumbs
```

Les menus sont définis dans des providers :

```text
src/
└── Navigation/
    ├── MenuItem.php
    ├── MenuResolver.php
    ├── MenuRegistry.php
    └── Provider/
        ├── AdministrationMenuProvider.php
        ├── DroitsMenuProvider.php
        ├── ConseilsMenuProvider.php
        ├── PilotageMenuProvider.php
        └── ...
```

---

# Définition d'un menu

## Menu simple

```php
MenuItem::section(
    key: 'droits',
    label: 'menu.menu_droits',
    route: 'app_section_droits',
    children: [
        MenuItem::link(
            key: 'droits.profils',
            label: 'menu.droits.profils',
            route: 'app_administration_profils_index',
        ),
    ],
)
```

---

# Propriétés disponibles

## key

Identifiant unique dans l'arbre.

```php
'droits.profils'
```

Utilisé notamment pour les breadcrumbs.

---

## label

Clé de traduction.

```php
'menu.droits.profils'
```

---

## route

Route Symfony associée.

```php
'app_administration_profils_index'
```

---

## routeParams

Paramètres de route.

```php
[
    'composante' => 12
]
```

---

## icon

Icône utilisée dans les pages de section.

```php
'mdi:account'
```

---

## description

Description affichée dans les pages de section.

```php
'menu.description.profils'
```

---

## position

Ordre d'affichage.

```php
->withPosition(10)
```

Exemple :

```text
10  Pilotage
20  Conseils
30  Droits
90  Administration
```

---

## column

Permet de regrouper les éléments dans :

* les mega-menus ;
* les pages de section.

Exemple :

```php
->inColumn('menu.config.offre_formation')
```

---

## displayMode

Modes disponibles :

```php
MenuDisplayModeEnum::Dropdown
MenuDisplayModeEnum::MegaMenu
```

Exemple :

```php
->asMegaMenu()
```

---

# Gestion des droits

## Menu complet

```php
MenuItem::section(...)
    ->requiresRole('ROLE_ADMIN');
```

Le menu entier disparaît si le rôle n'est pas présent.

---

## Élément individuel

```php
MenuItem::link(...)
    ->requiresRole('ROLE_ADMIN');
```

L'élément est supprimé du menu mais le menu reste visible.

---

## Permission métier

```php
MenuItem::link(...)
    ->requires(
        'EDIT',
        [
            'route' => 'app_composante',
            'subject' => 'composante',
        ]
    );
```

---

# Pages de section

Chaque section principale peut disposer d'une page d'orientation.

Exemple :

```text
Administration
 ├─ Établissement
 ├─ Composantes
 ├─ Villes
 └─ ...
```

Route :

```php
app_section_administration
```

Contrôleur :

```php
$menuItem = $menuResolver->findByKey('administration');
```

Twig :

```twig
{% for child in menuItem.children %}
```

Les colonnes sont automatiquement regroupées via :

```php
->inColumn(...)
```
