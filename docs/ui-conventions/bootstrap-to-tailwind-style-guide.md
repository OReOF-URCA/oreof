# Feuille de style - Conversion Bootstrap vers TailwindCSS

## Objectif

Ce guide definit les bonnes pratiques pour migrer les vues Bootstrap vers TailwindCSS dans OREOF, sans regression
fonctionnelle (Twig + Stimulus + Turbo).

## Principes de base

- Migrer **par ecran ou composant complet**, pas par petites classes isolees.
- Conserver la logique JS existante (`stimulus_controller`, `stimulus_action`, `data-turbo-*`).
- **Privilegier les Twig Components UI** avant d'ecrire du HTML Tailwind inline (`<twig:Button>`,
  `{{ component('alerte') }}`).
- Preferer les classes utilitaires Tailwind directement dans Twig.
- Extraire uniquement les patterns repetitifs dans `assets/styles/app.css` (`@layer components`).
- Garder une phase transitoire possible via classes `app-*` (`app-btn`, etc.).

## Twig Components d'abord

## Catalogue de Composants et Vues Types

Toutes les nouvelles pages doivent obligatoirement s'aligner sur l'architecture de référence de l'application.
Consulte les fichiers suivants pour copier la structure exacte de l'UI :

- **Composants atomiques (Boutons, Badges, Alertes)** : Analyse `templates/admin/styleguide/index.html.twig`. Tu as l'interdiction de générer des balises HTML brutes avec des classes Tailwind custom pour ces éléments.
- **Mise en page d'un CRUD / Index d'administration** : Copie l'agencement exact des blocs (breadcrumb, page_title, page_actions, content) décrit dans `templates/admin/styleguide/templates/index_type.html.twig`.

### Regle

- Si un composant existe, il est prioritaire sur le markup manuel.
- Cibles minimales obligatoires dans les migrations:
    - Boutons d'action: `<twig:Button ... />`
    - Messages utilisateur (info/succes/alerte): `{{ component('alerte', {...}) }}`

### Exemples recommandes

- Bouton:
    - `{{ '<twig:Button variant="success" icon="icon:add" label="Ajouter" href="..." />' }}`
- Alerte:
    - `{{ "{{ component('alerte', { type: 'warning', message: 'Attention...' }) }}" }}`

### Cas autorises (exception)

- Pas de composant existant pour le besoin.
- Besoin ponctuel de prototype rapide (a refactoriser ensuite).
- Extension complexe du composant non encore supportee.

### Cas a eviter

- Revenir a `btn btn-*` ou `alert alert-*` dans une vue migree.
- Melanger plusieurs systemes de boutons dans le meme bloc (`btn`, `app-btn`, `<twig:Button>`).

## Regles de migration

### 1) Structure / layout

- `container`, `row`, `col-*` -> `grid`, `flex`, `gap-*`, `sm:`, `lg:`, `xl:`.
- Exemple:
    - Bootstrap: `row g-3` + `col-md-6`
    - Tailwind: `grid grid-cols-1 gap-3 md:grid-cols-2`

### 2) Cartes / panneaux

- `card card-body` -> `rounded-xl border bg-white p-4 shadow-sm dark:*`
- Uniformiser avec ces tokens:
    - Bordure: `border-slate-200 dark:border-slate-700`
    - Fond: `bg-white dark:bg-slate-900`
    - Elevation: `shadow-sm`

### 3) Formulaires

- `form-control`, `form-select` -> classes Tailwind communes:
    - `w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm`
    - `focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100`
    - `dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100`
- Toujours garder un `label` (visuel ou `sr-only`).

### 4) Boutons

- Remplacer `btn btn-*` par:
    - soit utilitaires Tailwind
    - soit composants `app-btn app-btn-*` (recommande en migration progressive)
- Eviter les melanges `btn ... app-btn ...` dans un meme bouton.

### 5) Tableaux

- Wrapper obligatoire: `overflow-x-auto`.
- Table: `w-full min-w-* text-sm`.
- `thead`: texte compact (`text-[11px] uppercase tracking-*`).
- `tbody`: `divide-y divide-slate-200 dark:divide-slate-700`.
- Etats de ligne (information, warning, etc.) via classes Tailwind dediees.

### 6) Etats et badges

- Remplacer `badge bg-*` par des `span` Tailwind (`rounded-full`, `px-2.5`, `text-xs`, etc.).
- Si un filtre Twig renvoie encore du HTML Bootstrap, encapsuler temporairement avec styles `[&_.badge]:...`.

## Bootstrap JS -> Stimulus/Turbo

### A. Tooltips / Modals

- Ne pas dependre uniquement de `data-bs-*` sur des fragments Turbo.
- Preferer des controleurs Stimulus dedies (`tooltip`, `modal`) avec options explicites.

### B. Elements masques

- Si un controleur legacy toggle `d-none`, ajouter aussi `hidden` dans le markup Tailwind.
- Ou adapter le JS pour piloter les deux classes (`d-none` + `hidden`) pendant la transition.

## Accessibilite (obligatoire)

- Conserver `aria-*` existants.
- Boutons icone-only: ajouter `aria-label`.
- Champs sans label visuel: `label.sr-only`.
- Breadcrumb: dernier element non cliquable avec `aria-current="page"`.

## Convention de theme

- Toujours prevoir le dark mode sur nouveaux blocs:
    - `dark:bg-*`, `dark:text-*`, `dark:border-*`.

## Checklist PR (conversion Bootstrap -> Tailwind)

- [ ] Plus de classes Bootstrap structurelles (`row`, `col-*`, `card`, `form-control`, `btn`, etc.)
- [ ] `Button` et `alerte` passent par Twig Components quand applicables
- [ ] Toute exception composant est justifiee dans la PR
- [ ] Stimulus/Turbo inchanges et fonctionnels
- [ ] Etats visuels (hover, disabled, dark) traites
- [ ] Accessibilite verifiee (`label`, `aria`, contraste)
- [ ] Tableaux responsives (`overflow-x-auto`)
- [ ] Pas de debug markup residuel
- [ ] `get_errors` sans erreur bloquante

## Mapping rapide

| Bootstrap                | Twig Component / Tailwind (exemple)                              |
|--------------------------|------------------------------------------------------------------|
| `btn btn-success btn-sm` | `<twig:Button variant="success" size="sm" />`                    |
| `alert alert-warning`    | `{{ component('alerte', { type: 'warning', message: '...' }) }}` |
| `row g-3`                | `grid grid-cols-1 gap-3 md:grid-cols-2`                          |
| `col-md-3`               | `md:col-span-3`                                                  |
| `card`                   | `rounded-xl border bg-white shadow-sm`                           |
| `card-body p-3`          | `p-3`                                                            |
| `form-control`           | `w-full rounded-lg border px-3 py-2 text-sm`                     |
| `form-select`            | `w-full rounded-lg border px-3 py-2 text-sm`                     |
| `table table-striped`    | `w-full text-sm` + `divide-y` + `hover:bg-*`                     |
| `text-muted`             | `text-slate-500 dark:text-slate-400`                             |
