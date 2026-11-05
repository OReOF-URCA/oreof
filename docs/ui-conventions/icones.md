# Icônes : FontAwesome Pro → Symfony UX Icons

Catalogue officiel : **https://ux.symfony.com/icons**

Quand lire : remplacement d'un `<i class="fa…">` ou choix d'une icône dans un template.
À mettre à jour si : `config/packages/ux_icons.yaml` (alias ajouté/renommé/supprimé) ou directives sur les sets d'icônes.

## Règles & Priorités

- **Catalogue de référence** : Rechercher les icônes sur **https://ux.symfony.com/icons**.
- **Sets prioritaires** :
  1. **MDI** (`mdi:*`, ex: `mdi:routes`, `mdi:certificate`, `mdi:book-open-page-variant`, `mdi:sync`, `mdi:api`…)
  2. **Phosphor** (`ph:*`, ex: `ph:*-light` par défaut, `ph:*-bold` pour l'emphase, ex: `ph:house-light`, `ph:trash-light`…)
  3. D'autres sets d'Iconify (`simple-icons:*` pour les logos/marques, `material-symbols:*`) peuvent être utilisés si besoin.
- **Alias sémantiques (`icon:*`)** :
  - Définis dans `config/packages/ux_icons.yaml` (`aliases:`).
  - ⚠️ **Règle absolue** : `ignore_not_found: false` est actif en développement → **utiliser un `icon:*` non déclaré dans `ux_icons.yaml` lève immédiatement une exception 500** ! Ne jamais inventer un alias sémantique `icon:xyz` au vol (ex: `icon:direction`, `icon:alert`, `icon:book` n'existent pas et font planter).
  - Si un alias sémantique n'est pas déjà configuré dans `ux_icons.yaml`, **utiliser directement le nom du catalogue (ex: `mdi:routes`, `mdi:alert-circle`)**, ou ajouter l'alias explicitement dans `ux_icons.yaml`.
- **Usage en template** :
  - Composant Twig : `<twig:UX:Icon name="mdi:sync" class="w-4 h-4" />`
  - Fonction Twig : `{{ ux_icon('mdi:sync', {class: 'w-4 h-4'}) }}`
  - Dimensions et couleurs gérées via Tailwind (`w-4 h-4 text-primary-600`…).
- **Legacy FontAwesome** : FontAwesome n'est plus importé dans les assets (`assets/app.js`, `assets/styles/`) : les `<i class="fa…">` résiduels ne s'affichent plus et doivent être migrés systématiquement en `<twig:UX:Icon>`.

## Alias définis hors correspondance FA

`icon:add` `icon:check:bold` `icon:arrow-right` `icon:award` `icon:book-open` `icon:document` `icon:document-copy`
`icon:face-sad` `icon:file-text` `icon:git-pull-request` `icon:inbox` `icon:info-circle` `icon:layers` `icon:login` `icon:pencil`
`icon:delete` `icon:edit` `icon:duplicate` `icon:info` `icon:success` `icon:danger` `icon:gear` `icon:key`
`icon:mail` `icon:phone` `icon:refresh` `icon:upload` `icon:sort` `icon:sort-up` `icon:sort-down`
`icon:ellipsis-vertical` `icon:arrows-up-down-left-right` `icon:translate` `icon:chart` `icon:chart-pie`

## Correspondance FontAwesome → cible

Cible = alias existant, sinon icône Phosphor à utiliser directement (ou à déclarer en alias).

| FontAwesome | Cible |
|---|---|
| `fal fa-home` | `icon:home` |
| `fal fa-bars` | `icon:menu` |
| `fal fa-chevron-right` | `icon:chevron-right` |
| `fal fa-chevron-left` | `icon:chevron-left` |
| `fal fa-chevrons-right` | `icon:chevrons-right` |
| `fal fa-chevrons-left` | `icon:chevrons-left` |
| `fas fa-chevron-down` | `icon:chevron-down` |
| `fas fa-chevron-up` | `icon:chevron-up` |
| `fal fa-caret-right` | `ph:caret-right-light` |
| `fal fa-arrow-left` | `icon:arrow-left` |
| `fal fa-down` / `fas fa-arrow-down` | `ph:arrow-down-light` |
| `fal fa-up` | `ph:arrow-up-light` |
| `fas fa-arrow-down-arrow-up` | `ph:arrows-down-up-light` |
| `fal fa-forward` | `ph:fast-forward-light` |
| `fal fa-right-left` | `ph:arrows-left-right-light` |
| `fal fa-pencil` | `ph:pencil-light` |
| `fa-duotone fa-pencil-ruler` | `icon:pencil-ruler` |
| `fal fa-marker` | `ph:marker-light` |
| `fal fa-floppy-disk` | `icon:save` |
| `fal fa-magnifying-glass` / `fal fa-search` | `icon:search` |
| `fas fa-filter` | `icon:filter` |
| `fas fa-trash` | `icon:delete` / `icon:trash` |
| `fas fa-download` | `icon:download` |
| `fas fa-print` | `icon:print` |
| `fal fa-send` | `ph:paper-plane-tilt-light` |
| `fal fa-paper-plane` | `ph:paper-plane-light` |
| `fal fa-paper-plane-top` | `ph:paper-plane-tilt-light` |
| `fal fa-rotate` | `ph:arrows-clockwise-light` |
| `fal fa-rotate-left` | `ph:arrow-counter-clockwise-light` |
| `fal fa-rotate-right` / `fas fa-arrow-rotate-right` | `ph:arrow-clockwise-light` |
| `fal fa-undo` | `ph:arrow-counter-clockwise-light` |
| `fas fa-sync-alt` | `icon:sync` |
| `fal fa-random` | `ph:shuffle-light` |
| `fal fa-merge` | `ph:git-merge-light` |
| `fal fa-link` / `fal fa-chain` | `icon:link` |
| `fal fa-share-nodes` | `icon:share` |
| `fal fa-eye` / `fas fa-eye` | `icon:eye` |
| `fal fa-eye-slash` | `icon:eye-slash` |
| `fas fa-lock` | `icon:lock` |
| `fal fa-lock-open` | `ph:lock-open-light` |
| `fas fa-unlock` | `icon:unlock` |
| `fal fa-check` / `fas fa-check` | `icon:check` |
| `fas fa-check-circle` | `icon:check-circle` |
| `fal fa-close` / `fal fa-times` / `fas fa-close` | `icon:close` |
| `far fa-xmark-large` / `far fa-times` | `ph:x-light` |
| `fal fa-ban` | `ph:prohibit-light` |
| `fal fa-do-not-enter` | `ph:prohibit-inset-light` |
| `fas fa-exclamation-triangle` | `icon:warning` |
| `fal fa-question` / `fal fa-question-circle` | `icon:question` |
| `fas fa-skull-crossbones` | `ph:skull-bold` |
| `fal fa-circle-down` | `ph:arrow-circle-down-light` |
| `fal fa-circle-up` | `ph:arrow-circle-up-light` |
| `fas fa-circle-half-stroke` | `ph:circle-half-bold` |
| `fal fa-down-to-bracket` | `ph:arrow-line-down-light` |
| `fal fa-percent` | `ph:percent-light` |
| `fal fa-clock` / `fas fa-clock` | `icon:clock` |
| `fal fa-timeline` | `icon:timeline` |
| `fal fa-file` | `ph:file-light` |
| `fas fa-file-alt` | `ph:file-text-light` |
| `fas fa-file-code` | `ph:file-code-light` |
| `fas fa-folder-open` | `icon:folder` |
| `fas fa-list` | `ph:list-bold` |
| `fal fa-ballot-check` | `ph:clipboard-text-light` |
| `fal fa-memo-circle-check` | `ph:note-pencil-light` |
| `fas fa-chart-pie` | `ph:chart-pie-light` |
| `fas fa-project-diagram` | `ph:graph-light` |
| `fas fa-inbox` | `ph:tray-arrow-down-light` |
| `fas fa-comments` | `icon:comments` |
| `fas fa-comment` | `icon:comment` |
| `fal fa-binoculars` | `ph:binoculars-light` |
| `fas fa-ellipsis-v` | `ph:dots-three-vertical-bold` |
| `fal fa-shield-check` | `ph:shield-check-light` |
| `fas fa-user-check` | `ph:user-check-bold` |
| `fal fa-users` / `fas fa-users` | `icon:users` |
| `fal fa-users-between-lines` | `ph:users-three-light` |
| `fal fa-school` | `icon:school` |
| `fal fa-chalkboard-user` | `ph:chalkboard-teacher-light` |
| `fas fa-graduation-cap` | `icon:graduation` |
| `fas fa-door-open` | `ph:door-open-bold` |
| `fal fa-wrench` / `fas fa-wrench` | `icon:wrench` |
| `fas fa-cog` | `ph:gear-light` |
| `fal fa-cogs` | `ph:gear-light` |
| `fas fa-gears` | `ph:gear-six-bold` |
| `fas fa-gear-complex` | `ph:gear-six-bold` |
| `fal fa-bug` | `icon:bug` |
| `fal fa-bullhorn` | `ph:megaphone-light` |
| `fal fa-bell` | `icon:bell` |
| `fal fa-envelope` | `icon:envelope` |
| `fa-brands fa-github` | `icon:github` |
