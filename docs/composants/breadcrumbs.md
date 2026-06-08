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

# Breadcrumbs

## Génération automatique

Le breadcrumb est généré automatiquement à partir de l'arbre de navigation.

Exemple :

```php
MenuItem::link(
    key: 'droits.affectation_profils',
    route: 'app_user_profil_index'
)
```

Produit :

```text
Accueil
→ Droits
→ Affectation des profils
```

sans configuration supplémentaire.

---

## Cas nécessitant une déclaration

Les routes qui ne sont pas présentes dans le menu doivent indiquer leur rattachement.

Exemple :

```php
#[Breadcrumb(menuKey: 'droits.affectation_profils')]
```

Résultat :

```text
Accueil
→ Droits
→ Affectation des profils
→ Détail
```

---

## Cas dynamiques

Pour les entités métier :

```php
#[Breadcrumb(menuKey: 'offre.formations')]
```

Puis :

```php
$breadcrumb->add(
    $formation->getDisplay(),
    'app_formation_show',
    [
        'id' => $formation->getId(),
    ]
);
```

Résultat :

```text
Accueil
→ Offre de formation
→ Formations
→ BUT MMI
```

---

# Bonnes pratiques

## Toujours créer une section pour les grands domaines

Exemples :

```text
Pilotage
Conseils
Droits
Administration
```

---

## Toujours définir une clé unique

Préférer :

```php
administration.profils
administration.langues
administration.actualites
```

Éviter :

```php
profils
langues
actualites
```

---

## Utiliser les traductions

Toujours :

```php
label: 'menu.config.langues'
```

Jamais :

```php
label: 'Langues'
```

---

## Utiliser les colonnes pour les mega-menus

Exemple :

```php
->inColumn('menu.config.offre_formation')
```

Cela permet automatiquement :

* le mega-menu ;
* la page de section ;
* un futur export de navigation.

---

# Principe fondamental

Le menu est la source unique de vérité de la navigation.

Toute nouvelle fonctionnalité accessible depuis la navigation doit être ajoutée dans un provider afin que :

* la topbar soit mise à jour ;
* la page de section soit mise à jour ;
* les breadcrumbs soient générés automatiquement.
