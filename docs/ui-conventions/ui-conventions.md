# Conventions UI (Tailwind + Twig Components)

Quand lire : toute création/modification de template Twig, CSS ou composant UI, et toute migration Bootstrap → Tailwind.
À mettre à jour si : `src/Twig/Components/UI/`, `templates/components/_ui/`, `templates/admin/styleguide/`, classes `app-*` ou tokens de `assets/styles/app.css`.

## Sources de vérité

- Démo vivante de tous les composants et couleurs : `templates/admin/styleguide/index.html.twig`.
- Gabarit de page CRUD/index (blocs breadcrumb, page_title, page_actions, content) :
  `templates/admin/styleguide/templates/index_type.html.twig` — à dupliquer pour toute nouvelle page.
- Styles : `assets/styles/app.css` (sources Tailwind, dark mode `html[data-theme="dark"]`, classes `app-*`, compat
  Bootstrap, thème Tom Select `.ts-*`).
- Composants UI : `src/Twig/Components/UI/*.php` → `templates/components/_ui/*.html.twig`.

## Règles

- Composant existant > markup manuel. Interdit de générer boutons/badges/alertes en HTML brut.
- Aucun nouveau Bootstrap (`btn`, `badge`, `alert`, `card`, `row`, `col-*`, `form-control`…) ; ne pas mélanger `btn`,
  `app-btn` et `<twig:Button>` dans un même bloc.
- Couleur = intention : `primary` action principale · `success` ajout/validation · `warning` édition/attention ·
  `danger` suppression · `info` consultation · `secondary` neutre.
- Palettes **sémantiques uniquement** (`primary|secondary|success|info|warning|danger`, nuances 50–950, variables dans
  `app.css`) ; jamais de couleur Tailwind brute (`blue`, `emerald`, `gray`, `slate`…). Correspondance : emerald/green →
  success, cyan/sky → info, amber/orange → warning, rose/red → danger, blue/indigo → primary, gray/slate → secondary.
- Dark mode obligatoire sur tout nouveau bloc (`dark:bg-* dark:text-* dark:border-*`). Les nuances 50–400 restent
  claires en sombre : `bg-white` → `bg-surface`. Pastel (`border-<s>-300 bg-<s>-50 text-<s>-700`), reprendre les
  classes des composants de référence :
  - badge (`components/_ui/badge.html.twig`) : `dark:border-<s>-700 dark:bg-<s>-900/30 dark:text-<s>-300` ;
  - bouton soft (`UI/Button.php`) : `dark:border-<s>-500 dark:bg-<s>-900 dark:text-<s>-300 dark:hover:border-<s>-300
    dark:hover:text-<s>-100`.
  Mieux : utiliser directement `<twig:Badge>` / `<twig:Button>`.
- Migrer un écran/composant entier, jamais classe par classe ; conserver `stimulus_*`, `data-turbo-*`, `aria-*`.
- Motif répété → classe dans `app.css` via `@layer components` + `@apply` ; réutiliser `app-btn*`, `app-tabs`,
  `app-section-*`, `app-alert*`, `app-progress*`, `app-modal*`.
- Tom Select : ne pas réactiver ses CSS par défaut (désactivés dans `assets/controllers.json`) ; vérifier focus,
  disabled, multi, dropdown, dark.
- Exception au composant tolérée seulement si : aucun composant ne couvre le besoin, prototype à refactoriser, ou
  extension non supportée — à justifier dans la PR.

## Composants UI

| Tag | Props principales |
|---|---|
| `<twig:Button>` | `label`, `variant`, `size` sm/md/lg, `soft` (défaut true), `outline`, `icon`, `iconEnd`, `href`, `type` button/submit, `fullWidth`, `centered`, `tooltip`, `disabled`, `extraClass`, `dataAction` |
| `<twig:Badge>` | `label`, `variant`, `size` sm/md, `soft` (true), `pill` (true), `icon`, `iconEnd`, `extraClass` |
| `<twig:DownloadCard>` | `title`*, `href`*, `type` word/excel/pdf/powerpoint/archive/video/audio/image/link/file, `description`, `meta`, `variant`, `cta`, `icon`, `target` (_blank) |
| `<twig:Kpi>` | `title`*, `value`*, `total`, `unit`, `percent` (affiche une jauge), `variant` (+`default`), `icon`, `description`, `href`, `extraClass` |
| `{{ component('alerte', {type, message}) }}` | messages info/succès/alerte |
| `Card`, `DeleteButton`, `Dot`, `IconBox`, `Select`, `Spinner`, `dropdown_actions` | voir la classe PHP et le styleguide |

Composants métier (badges ECTS/heures/MCCC, headers formation/parcours, `RemplissageProgress`…) : `src/Twig/Components/`.

```twig
<twig:Button label="Ajouter" variant="success" icon="icon:add" href="{{ path('...') }}" />
<twig:Button label="Enregistrer" variant="success" :soft="false" type="submit" icon="icon:save" />
<twig:Badge :label="isPublished ? 'Publié' : 'Brouillon'" :variant="isPublished ? 'success' : 'warning'" />
<twig:Kpi title="Validées" :value="28" description="Pour publication" variant="success" icon="icon:check-circle" />
```

Grilles : cartes `grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3` ; KPI `grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6`.

## Actions CRUD

| Action | variant | icon |
|---|---|---|
| show | `info` | `icon:info` |
| edit | `warning` | `icon:edit` |
| duplicate | `success` | `icon:duplicate` |
| delete | `danger` | `icon:delete` |
| new/create | `success` | `icon:add` |

## Tokens Tailwind

- Section/panneau : `rounded-xl border border-secondary-200 bg-surface p-4 shadow-sm dark:border-secondary-700`.
- Fonds `bg-bg` / `bg-surface`, texte `text-text` (dark auto) ; texte secondaire `text-secondary-500 dark:text-secondary-400`.
- Espacement vertical : `space-y-4` ou `space-y-6`. État vide : texte court `text-secondary-400`.
- Champ : `w-full rounded-lg border border-secondary-200 bg-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-2
  focus:ring-cyan-100 dark:border-secondary-700 dark:bg-secondary-800 dark:text-secondary-100` ; toujours un `label`
  (visible ou `sr-only`).
- Tableau : wrapper `overflow-x-auto` obligatoire ; `table w-full text-sm` ; `thead` `text-[11px] uppercase tracking-*` ;
  `tbody divide-y divide-secondary-200 dark:divide-secondary-700` ; conserver les hooks Stimulus de tri/filtre.

### Opacité (piège)

Les couleurs sémantiques (`primary`, `secondary`, `info`…) sont des variables `color-mix()` : la syntaxe
`bg-primary-600/45` **n'applique pas** l'opacité. Utiliser une classe séparée : `bg-primary-600 opacity-45`
(+ `dark:opacity-*`). Overlays : fort 45–50, moyen 35–40, faible 20–25, discret 10–15. Vérifier le contraste en light
et dark.

## Mapping Bootstrap → Tailwind

| Bootstrap | Remplacement |
|---|---|
| `btn btn-success btn-sm` | `<twig:Button variant="success" size="sm" />` |
| `alert alert-warning` | `{{ component('alerte', { type: 'warning', message: '…' }) }}` |
| `badge bg-*` | `<twig:Badge>` (si un filtre Twig renvoie encore du HTML Bootstrap : wrapper `[&_.badge]:…` temporaire) |
| `row g-3` / `col-md-6` | `grid grid-cols-1 gap-3 md:grid-cols-2` |
| `col-md-3` | `md:col-span-3` |
| `card` / `card-body p-3` | voir « Section/panneau » / `p-3` |
| `form-control`, `form-select` | voir « Champ » |
| `table table-striped` | voir « Tableau » + `hover:bg-*` |
| `text-muted` | `text-secondary-500 dark:text-secondary-400` |
| `d-none` togglé par du JS legacy | ajouter aussi `hidden`, ou adapter le JS pour piloter les deux |
| `data-bs-*` (tooltip/modal) dans un fragment Turbo | contrôleur Stimulus dédié (`tooltip`, `modal`) |

## Accessibilité

Bouton icône seule → `aria-label` ; conserver les `aria-*`/`role` existants ; breadcrumb : dernier élément non cliquable
avec `aria-current="page"` ; onglets, dropdowns, tableaux et progress bars utilisables au clavier.

## Checklist PR UI

- [ ] Aucune classe Bootstrap ajoutée ; boutons/badges/alertes via composants ; variante = intention
- [ ] Hooks Stimulus/Turbo/`id`/`aria-*` intacts ; compat `turbo-frame`/`turbo-stream` vérifiée si partiel
- [ ] Hover, disabled et dark mode traités ; tableaux responsives
- [ ] Accessibilité (`label`, `aria-label`, contraste) ; pas de markup de debug
- [ ] `npm run dev` OK
