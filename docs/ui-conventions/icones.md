# Icônes : FontAwesome Pro → Symfony UX Icons

Quand lire : remplacement d'un `<i class="fa…">` ou choix d'une icône dans un template.
À mettre à jour si : `config/packages/ux_icons.yaml` (alias ajouté/renommé/supprimé) ou fin de migration FontAwesome.

## Règles

- Source de vérité des alias : `config/packages/ux_icons.yaml` (`aliases:`). `ignore_not_found: false` → **un alias
  inexistant lève une exception au rendu** : vérifier qu'il existe avant de l'utiliser.
- Priorité : alias sémantique `icon:*` s'il existe ; sinon nom Phosphor direct (`ph:*-light` par défaut, `-bold` pour
  l'emphase) ; sinon ajouter l'alias dans `ux_icons.yaml`. Autres sets disponibles localement : `assets/icons/`.
- **Tout `icon:*` utilisé doit être déclaré dans `ux_icons.yaml`**, sinon UX Icons le cherche sur iconify.design sous
  le préfixe `icon` et lève « Icon not found » (cas de `/aide` en pré-prod). `assets/icons/` est ignoré par git : les
  cibles `ph:*` sont récupérées depuis iconify à la volée ; vérifier que le nom existe
  (`node_modules/@iconify-json/ph/icons.json`).
- Usage : `<twig:UX:Icon name="icon:save" class="w-4 h-4" />` (taille/couleur via classes Tailwind).
- Variantes d'alias existantes : `icon:{danger,info,question,success,warning}:bold`.
- FontAwesome n'est plus importé dans les assets (`assets/app.js`, `assets/styles/`) : les `fa-*` encore présents dans `templates/` ne s'affichent plus
  et sont à migrer (`grep -rn 'class="fa' templates/`).

## Alias définis hors correspondance FA

`icon:add` `icon:check:bold` `icon:arrow-right` `icon:award` `icon:book-open` `icon:document` `icon:document-copy`
`icon:face-sad` `icon:file-text` `icon:git-pull-request` `icon:inbox` `icon:info-circle` `icon:layers` `icon:login` `icon:pencil`
`icon:delete` `icon:edit` `icon:duplicate` `icon:info` `icon:success` `icon:danger` `icon:gear`
`icon:mail` `icon:phone` `icon:refresh` `icon:upload` `icon:sort` `icon:sort-up` `icon:sort-down`
`icon:ellipsis-vertical` `icon:arrows-up-down-left-right` `icon:translate`

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
| `fas fa-trash` | `ph:trash-light` |
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
