# Conventions UI (Tailwind + Twig Components)

Quand lire : toute création/modification de template Twig, CSS ou composant UI, et toute migration Bootstrap → Tailwind.
À mettre à jour si : `src/Twig/Components/UI/`, `templates/components/_ui/`, `templates/admin/styleguide/`, `templates/base.html.twig` (en-tête de page), `translations/header.fr.yaml`, `assets/controllers/page_header_controller.js`, `assets/js/chartTheme.js`, classes `app-*` ou tokens de `assets/styles/app.css`, `assets/styles/_timeline.scss`, `templates/default/_timeLine.html.twig`.

## Sources de vérité

- Démo vivante de tous les composants et couleurs : `templates/admin/styleguide/index.html.twig`.
- Gabarit de page CRUD/index (en-tête, bloc `bouton_header`, `content`) :
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
  `app-section-*`, `app-alert*`, `app-progress*`, `app-modal*`, `app-timeline*`.
- Tom Select : ne pas réactiver ses CSS par défaut (désactivés dans `assets/controllers.json`) ; vérifier focus,
  disabled, multi, dropdown, dark.
- Select de **filtre** (liste déroulante qui relance une liste) : pas de `<select>` natif (popup OS illisible en
  sombre, et menu natif qui peut s'ouvrir vers le haut et se bloquer sur petit écran, ex. `formation/new.html.twig`) ; monter Tom Select avec `{{ stimulus_controller('symfony/ux-autocomplete/autocomplete') }}` et ajouter la
  classe `app-filter-select` (aspect « champ », cf. `app.css`) ; `app-select-required` en plus si aucune option vide
  n'est valide (masque le bouton « × »). Si le JS remplace les `<option>`, resynchroniser via
  `select.tomselect` (cf. `fillFilterSelect()` dans `offre_v2/synthese_offre.html.twig`). Les `<select>` natifs restants
  sont thémés globalement (`select`/`option` dans `app.css`).
  Les menus Tom Select tiennent dans la fenêtre (hauteur réduite, ouverture vers le haut si la place manque :
  `assets/js/tomSelectViewport.js`) et, dans une modale (`.app-modal-body`, `.modal`), sont rattachés au `<body>`
  pour ne pas être coupés. Champ en erreur de validation : `selectInvalid(id, bool)` (`assets/js/selectInvalid.js`)
  pose `is-invalid` sur le wrapper Tom Select. Champ de formulaire Symfony (`EntityType`/`ChoiceType`) :
  option `'autocomplete' => true`.
- Groupe de choix exclusifs (boutons `app-modal-footer-btn--secondary`, ex. panneau accessibilité) : signaler l'option
  active avec `aria-pressed="true"` (style dans `app.css`) + icône `icon:check` en `hidden group-aria-pressed:inline-block`.
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
| `<twig:PageHeader>` | `titleKey`/`title`, `descriptionKey`/`description`, `translationParams`, `translationDomain` (`header`), `breadcrumb` (true), `sticky` (true), bloc/prop `actions` — voir « En-tête de page » |
| `Card`, `DeleteButton`, `Dot`, `IconBox`, `Select`, `Spinner`, `dropdown_actions` | voir la classe PHP et le styleguide |

Timeline de campagne (tableau de bord, fiche campagne) : partial `templates/default/_timeLine.html.twig` (`include`, variable optionnelle `campagne`), rendu 100 % serveur, styles `app-timeline*` dans `assets/styles/_timeline.scss` (étapes `--completed|--current|--upcoming`, `--tl-progress` 0–1 pilote le remplissage du trait).

Composants métier (badges ECTS/heures/MCCC, headers formation/parcours, `RemplissageProgress`…) : `src/Twig/Components/`.

```twig
<twig:Button label="Ajouter" variant="success" icon="icon:add" href="{{ path('...') }}" />
<twig:Button label="Enregistrer" variant="success" :soft="false" type="submit" icon="icon:save" />
<twig:Badge :label="isPublished ? 'Publié' : 'Brouillon'" :variant="isPublished ? 'success' : 'warning'" />
<twig:Kpi title="Validées" :value="28" description="Pour publication" variant="success" icon="icon:check-circle" />
```

Grilles : cartes `grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3` ; KPI `grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6`.

## Graphiques (`symfony/ux-chartjs`)

- `ChartBuilderInterface` côté contrôleur + `{{ render_chart(chart, {role: 'img', 'aria-label': '…'}) }}` dans un
  conteneur à hauteur fixe (`h-56`, option `maintainAspectRatio: false`).
- Couleurs : jamais de hex dans le PHP. Déclarer `'colorToken' => 'primary'` sur le dataset : `assets/js/chartTheme.js`
  résout les tokens sémantiques (marques, grille, graduations) et recolore à chaud en sombre / thème de couleur. Un
  nouveau token = une entrée dans `MARK_CLASSES` (classes écrites en clair pour que Tailwind les émette). Définir
  `scales.x` / `scales.y` pour que les axes soient thémés.
- Une série par graphique (pas de double axe) ; plusieurs séries = palette à valider, `success`/`warning`/`danger`
  réservés aux statuts. Fournir une vue tableau des données (ex. `<details>`).

## En-tête de page (`PageHeader`)

`base.html.twig` affiche l'en-tête (titre, description, boutons, fil d'Ariane) via `<twig:PageHeader>` : **ne pas recréer
cet en-tête dans une page**. Il est sticky par défaut sous la topbar ; une fois collé (`data-stuck="true"`, contrôleur
Stimulus `page-header`) la description est masquée et le titre réduit ; actions et fil d'Ariane restent.
Sur une page trop courte pour absorber la hauteur perdue (défilement restant sous le seuil), l'en-tête reste collé mais
n'est pas réduit, pour éviter une oscillation.

- Titre/description = **clés de traduction**, domaine `header` (`translations/header.fr.yaml`), résolues par route :
  `<route>.title` / `<route>.description` (repli : `page.<route>.*` du domaine `menu`, puis nom de route). Une page
  sans description n'en affiche pas. Ne pas écrire dans `page.<route>.*` : ces clés servent aussi aux libellés de menu
  et breadcrumbs (`MenuItem`).
- Titre dynamique : `{% set header_title = 'app_xxx.title'|trans({'%libelle%': entite.libelle}, 'header') %}` avec
  `app_xxx.title: 'Détail : %libelle%'`. Template partagé par plusieurs routes à titres différents ou sans route
  détectable : clé `tpl.<chemin.du.template>.title`. Titre avec balisage (`<strong id=…>`, badge) : ne traduire que le
  texte, garder le balisage et ses `id` dans le template.
- Offset sticky : `top-(--topbar-height)` (variable définie dans `app.css`, 4rem ; 7rem quand la topbar est sur deux
  lignes) ; le contrôleur `page-header` mesure `.topbar-nav` et publie `--page-header-offset`. Ne pas coder `top-16`.
- Boutons d'action : bloc `{% block bouton_header %}` de la page. Options : `{% set header_breadcrumb = false %}`
  (supprime aussi le trait de séparation), `{% set header_sticky = false %}`.
- Usage direct (hors `base`) : `<twig:PageHeader titleKey="…" :sticky="false"><twig:block name="actions">…</twig:block></twig:PageHeader>`.

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

### Contrat du helper de saisie legacy

`assets/js/saveData.js` reste utilisé dans v2 et affiche ses messages via callOut/Toast. Il renvoie les données JSON
en cas de succès, false en cas d’échec HTTP/applicatif/réseau ; un rapport métier `error[]` est conservé pour les
consommateurs existants mais n’annonce pas un succès. Les wrappers `_save` propagent le résultat et ne recalculent
pas les indicateurs si le helper signale un échec. Un appelant indirect doit aussi vérifier ce résultat avant de
fermer, rafraîchir ou rediriger. Voir `docs/testing/audit-form-feedback.md` pour les chemins restant à migrer.

### Retours de formulaire et modales

Le thème `communs/form_theme_v2.html.twig` rend une synthèse des erreurs à l’ouverture du formulaire (`form_start`)
via `communs/_form_error_summary.html.twig`. Les erreurs globales et imbriquées restent visibles près de la saisie ;
les erreurs de champs proposent un lien. Le contrôleur `form-errors` place le focus sur la synthèse lorsqu’elle
est insérée (page ou modale), puis permet de rejoindre un contrôle, y compris Tom Select et un panneau `tabs`.
Les widgets spécialisés sans contrôle identifiable restent à adapter au cas par cas.

`form-feedback` sur le body suit les soumissions Turbo : état `aria-busy` restauré après traitement et message
local persistant si la réponse échoue sans synthèse. Aucun succès générique n’est inventé : les confirmations
restent celles du serveur. En cas d’erreur réseau, ne pas réessayer automatiquement un POST : le serveur peut
l’avoir traité. La saisie reste présente ; inviter à vérifier l’état avant un nouvel envoi.

`TurboStreamResponseFactory::streamOpenModalFromTemplates` retourne 422 si un `FormView` invalide est trouvé dans
le contexte, tant qu’aucun autre statut n’a été demandé. Les réponses restent des Turbo Streams et la modale ne
se ferme pas. Les erreurs métier des formulaires non Symfony peuvent utiliser `data-form-error-summary` et
`data-controller="form-errors"` sur leur panneau d’erreurs (exemple : validation de l’offre).

`handleToastResponse` expose `success` en plus de `handled`/`format`. Un HTTP en erreur, un JSON `success:false`
ou un stream portant les marqueurs d’erreur ne confirme jamais un succès. L’ancien gestionnaire de modales attend
ce résultat avant de fermer. Un nouvel endpoint doit retourner un vrai statut d’erreur et une synthèse explicite.

Les toasts danger restent affichés jusqu’à fermeture (`timeout: 0`, surcharge explicite possible). Dans une modale
active, ils sont déplacés dans `modal_body` pour rester perceptibles et accessibles malgré l’arrière-plan inactif.
Les autres toasts suspendent leur délai au survol et au focus. Une erreur de champ doit figurer dans le formulaire,
pas uniquement dans un toast.

### Aides et erreurs de formulaire

Le rendu Symfony reste centralisé dans `templates/communs/form_theme_v2.html.twig` :
- Les labels simples pointent vers l’ID du champ ; le label de création inline pointe vers le select d’entité.
- Les lignes standard, checkbox et création inline associent au widget uniquement les aides effectivement rendues
  (`<id>_help`) et les erreurs présentes (`<id>_errors`), en conservant les descriptions fournies par l’appelant.
  Un champ avec erreurs reçoit `aria-invalid="true"`. Les choix développés transmettent les descriptions aux options.
- La résolution des clés d’aide (dont celles du workflow) est commune ; le domaine `help` est conservé.
- Les erreurs sont lisibles en clair/sombre, sans capitales forcées. Les checkbox n’imbriquent plus deux labels.
  L’éditeur Trix possède l’ID attendu par son label et reçoit les attributs du widget.
- Pour les champs `textarea` et `trix-editor`, si un attribut `maxlength` est défini (dans `attr.maxlength` ou `maxLength`),
  un compteur de caractères s'affiche automatiquement sous le champ (`X / MAX caractères`), géré par le contrôleur
  Stimulus `char-count` : coloration d'avertissement à 90 %, passage en rouge avec mention « (limite atteinte) » et blocage
  effectif de la saisie clavier et du collage au-delà de la limite. Sans `maxlength`, aucun compteur n'est affiché.

Les composants hors Symfony Form (`Select`, formulaire de recherche) relient aussi leur aide via `aria-describedby`.
Un widget personnalisé qui n’utilise pas le rendu standard doit propager les attributs reçus vers son contrôle
ou son groupe accessible ; cette PR ne constitue pas une validation exhaustive de tous les widgets spécialisés.

### Statuts de sauvegarde des capacités

`offre_v2/configurer.html.twig` utilise une zone `role="status"` / `aria-live="polite"` atomique : état initial,
modifications en attente, enregistrement, succès ou échec. Les messages restent visibles jusqu’à l’état suivant.
En cas d’erreur, un bouton « Réessayer » relance la sauvegarde ; la saisie seule n’efface pas l’erreur affichée.
Le contrôleur `autosave_capacites` compare la révision envoyée à la saisie courante avant d’annoncer un succès.
Les messages du serveur sont insérés comme texte. Les écouteurs sont retirés à la déconnexion Stimulus.

### Filtres repliables

`components/filter_panel.html.twig` utilise un bouton `Button` (`data-crud-panel-toggle`) avec `aria-expanded`
et `aria-controls`, relié au bloc de champs identifié par `fieldsId`. Le contrôleur `filter-panel` synchronise
cet état avec la visibilité (`hidden`), sans checkbox masquée. Les filtres actifs ouvrent le panneau au rendu initial.

Les blocs `actions` redéfinis dans `formation/_liste.html.twig` et `formation/_fichesListe.html.twig` utilisent
les mêmes attributs et variables (`toggleId`, `fieldsId`, `isOpen`). Garder ces hooks lors d’une surcharge.
Le CRUD conserve le panneau pendant les rechargements AJAX ; l’ouverture choisie et les IDs restent stables,
seul le contenu du bouton (compteur) est rafraîchi par `_swapAroundPanel`.

Navigation principale et lien d’évitement : voir `docs/composants/navigation.md`.

### Navigation clavier des composants existants

- `dropdown_actions` / contrôleur `dropdown` : ouverture au clic ou avec Haut/Bas, focus sur la première/dernière
  action, déplacement Haut/Bas et Début/Fin (actions désactivées ignorées), Échap ferme et rend le focus au bouton.
  Tab reprend la navigation depuis le déclencheur malgré le menu déplacé dans `body`. Les IDs et `aria-controls`
  sont reliés au montage. Le positionnement, les formulaires POST/CSRF et les hooks Turbo restent inchangés.
- `tabs` : Gauche/Droite (sens RTL respecté), Début/Fin ; Haut/Bas pour un `tablist` avec
  `aria-orientation="vertical"`. L’activation au clavier conserve le comportement de fragment d’URL existant.
  Les touches provenant des champs des panneaux ne changent pas d’onglet.
- Modale Turbo (`templates/_ui/modal_wrapper.html.twig`, `modalturbo_controller.js`) : dialogue nommé par
  `modal_title`, focus initial sur le wrapper, Tab/Shift+Tab confinés et arrière-plan `inert` pendant l’ouverture.
  La fermeture restaure le focus (bouton du menu si ouverture depuis une action), l’état inert et le verrouillage
  du défilement antérieurs ; elle annule aussi le chargement en cours. Échap ferme d’abord une liste Tom Select
  ouverte ; les menus d’actions consomment Échap avant la modale. Les popups possédés par la modale peuvent rester
  dans `body` : pas de migration vers `<dialog>`. Les IDs de fragments et l’événement `modal:close` sont conservés.
- Breadcrumb : le séparateur est décoratif (`aria-hidden`) ; seul le dernier niveau porte `aria-current="page"`,
  y compris si d’autres niveaux ne possèdent pas de route. Les liens ont un focus visible.

Vérifier sur l’application : clavier dans une liste avec actions et POST, modale ouverte depuis un menu, formulaires
avec Tom Select et retour du focus après fermeture ; navigation Turbo et thèmes clair/sombre.
