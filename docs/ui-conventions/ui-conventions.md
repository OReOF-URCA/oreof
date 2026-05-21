# Conventions UI (Tailwind + Twig Components)

Objectif: harmoniser les pages ORéOF (boutons, badges, formulaires, tables, actions CRUD) pour reduire les variations
visuelles et accelerer les evolutions.

## 1) Regles globales

- Utiliser les composants Twig UI avant d'ecrire des classes inline.
- Eviter Bootstrap (`btn`, `badge`, `form-control`, `card`) sur les nouvelles vues.
- Garder un schema de couleur constant par intention:
    - `primary`: action principale
    - `success`: ajout, validation, etat positif
    - `warning`: edition, attention
    - `danger`: suppression, action destructive
    - `info`: consultation, information
    - `secondary`: action neutre
- Toujours definir un libelle explicite (accessibilite) et un etat `disabled` si necessaire.

## 2) Boutons

Composant: `src/Twig/Components/UI/Button.php`
Template: `templates/components/_ui/button.html.twig`
Usage: `<twig:Button ... />`

### API recommandee

- `variant`: `primary|success|warning|danger|info|secondary`
- `size`: `sm|md|lg`
- `soft`: `true` par defaut (style teinte)
- `outline`: bordure seule
- `icon`: nom icone (`icon:add`, `icon:edit`, ...)
- `iconEnd`: icone a droite
- `href`: rend un lien
- `type`: `button|submit`
- `fullWidth`: bouton pleine largeur
- `centered`: centre le contenu
- `tooltip`, `disabled`, `extraClass`, `dataAction`

### Exemples

```twig
<twig:Button label="Ajouter" variant="success" icon="icon:add" href="{{ path('...') }}" />
<twig:Button label="Enregistrer" variant="success" :soft="false" type="submit" icon="icon:save" />
<twig:Button label="Supprimer" variant="danger" :soft="false" icon="icon:delete" />
```

## 3) Badges

Composant: `src/Twig/Components/UI/Badge.php`
Template: `templates/components/_ui/badge.html.twig`
Usage: `<twig:Badge ... />`

### API recommandee

- `label`: texte affiche
- `variant`: `primary|success|warning|danger|info|secondary`
- `size`: `sm|md`
- `soft`: `true` par defaut (teinte), `false` pour fond plein
- `pill`: `true` par defaut
- `icon`, `iconEnd`, `extraClass`

### Exemples

```twig
<twig:Badge label="Oui" variant="success" icon="icon:check" />
<twig:Badge label="Non" variant="secondary" />
<twig:Badge :label="isPublished ? 'Publie' : 'Brouillon'" :variant="isPublished ? 'success' : 'warning'" />
```

## 4) Conventions CRUD

Pour les actions standard:

- `show`: `variant="info"` + `icon:info`
- `edit`: `variant="warning"` + `icon:edit`
- `duplicate`: `variant="success"` + `icon:copy`
- `delete`: `variant="danger"` + `icon:delete`
- `new/create`: `variant="success"` + `icon:add`

## 5) Structure de page

- Contenu principal dans des sections `rounded-xl border ... shadow-sm`.
- Espacement vertical standard: `space-y-4` ou `space-y-6`.
- Champs formulaires: classes Tailwind unifiees (focus ring + dark mode).
- Etats vides: texte court + style discret (`text-slate-400`).

## 6) Migration progressive

1. Migrer les vues frequentes (CRUD admin) en priorite.
2. Remplacer les badges boolens par `<twig:Badge>`.
3. Remplacer les boutons ad hoc par `<twig:Button>`.
4. En review, refuser les nouveaux styles inline si un composant existe deja.

## 7) Checklist PR UI

- [ ] Pas de classes Bootstrap ajoutees
- [ ] Boutons via `<twig:Button>`
- [ ] Badges via `<twig:Badge>` si possible
- [ ] Variante couleur conforme a l'intention
- [ ] Accessibilite minimale (`label`, `aria-label`, `disabled`)
