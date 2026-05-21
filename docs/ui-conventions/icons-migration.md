# Migration icônes : FontAwesome Pro → Phosphor / UX Icons

## Usage Twig

```twig
{# Avant (FontAwesome Pro) #}
<i class="fal fa-home icon"></i>
<i class="fas fa-check"></i>
<i class="fa-brands fa-github"></i>

{# Après (Symfony UX Icons + alias sémantiques) #}
<twig:UX:Icon name="icon:home" class="icon" />
<twig:UX:Icon name="icon:check" />
<twig:UX:Icon name="icon:github" />

{# Avec taille Tailwind #}
<twig:UX:Icon name="icon:home" class="w-5 h-5" />
<twig:UX:Icon name="icon:pencil-ruler" class="w-6 h-6 text-indigo-500" />
```

## Table de correspondance complète

| Alias sémantique       | FontAwesome Pro                                     | Phosphor / Simple Icons            |
|------------------------|-----------------------------------------------------|------------------------------------|
| `icon:home`            | `fal fa-home`                                       | `ph:house-light`                   |
| `icon:menu`            | `fal fa-bars`                                       | `ph:list-light`                    |
| `icon:chevron-right`   | `fal fa-chevron-right`                              | `ph:caret-right-light`             |
| `icon:chevron-left`    | `fal fa-chevron-left`                               | `ph:caret-left-light`              |
| `icon:chevrons-right`  | `fal fa-chevrons-right`                             | `ph:caret-double-right-light`      |
| `icon:chevrons-left`   | `fal fa-chevrons-left`                              | `ph:caret-double-left-light`       |
| `icon:chevron-down`    | `fas fa-chevron-down`                               | `ph:caret-down-light`              |
| `icon:chevron-up`      | `fas fa-chevron-up`                                 | `ph:caret-up-light`                |
| `icon:caret-right`     | `fal fa-caret-right`                                | `ph:caret-right-light`             |
| `icon:arrow-left`      | `fal fa-arrow-left`                                 | `ph:arrow-left-light`              |
| `icon:arrow-down`      | `fal fa-down` / `fas fa-arrow-down`                 | `ph:arrow-down-light`              |
| `icon:arrow-up`        | `fal fa-up`                                         | `ph:arrow-up-light`                |
| `icon:arrow-up-down`   | `fas fa-arrow-down-arrow-up`                        | `ph:arrows-down-up-light`          |
| `icon:forward`         | `fal fa-forward`                                    | `ph:fast-forward-light`            |
| `icon:right-left`      | `fal fa-right-left`                                 | `ph:arrows-left-right-light`       |
| `icon:pencil`          | `fal fa-pencil`                                     | `ph:pencil-light`                  |
| `icon:pencil-ruler`    | `fa-duotone fa-pencil-ruler`                        | `ph:pencil-ruler-duotone`          |
| `icon:marker`          | `fal fa-marker`                                     | `ph:marker-light`                  |
| `icon:save`            | `fal fa-floppy-disk`                                | `ph:floppy-disk-light`             |
| `icon:search`          | `fal fa-magnifying-glass` / `fal fa-search`         | `ph:magnifying-glass-light`        |
| `icon:filter`          | `fas fa-filter`                                     | `ph:funnel-light`                  |
| `icon:trash`           | `fas fa-trash`                                      | `ph:trash-light`                   |
| `icon:download`        | `fas fa-download`                                   | `ph:download-simple-light`         |
| `icon:print`           | `fas fa-print`                                      | `ph:printer-light`                 |
| `icon:send`            | `fal fa-send`                                       | `ph:paper-plane-tilt-light`        |
| `icon:paper-plane`     | `fal fa-paper-plane`                                | `ph:paper-plane-light`             |
| `icon:paper-plane-top` | `fal fa-paper-plane-top`                            | `ph:paper-plane-tilt-light`        |
| `icon:rotate`          | `fal fa-rotate`                                     | `ph:arrows-clockwise-light`        |
| `icon:rotate-left`     | `fal fa-rotate-left`                                | `ph:arrow-counter-clockwise-light` |
| `icon:rotate-right`    | `fal fa-rotate-right` / `fas fa-arrow-rotate-right` | `ph:arrow-clockwise-light`         |
| `icon:undo`            | `fal fa-undo`                                       | `ph:arrow-counter-clockwise-light` |
| `icon:sync`            | `fas fa-sync-alt`                                   | `ph:arrows-clockwise-bold`         |
| `icon:random`          | `fal fa-random`                                     | `ph:shuffle-light`                 |
| `icon:merge`           | `fal fa-merge`                                      | `ph:git-merge-light`               |
| `icon:link`            | `fal fa-link` / `fal fa-chain`                      | `ph:link-light`                    |
| `icon:share`           | `fal fa-share-nodes`                                | `ph:share-network-light`           |
| `icon:eye`             | `fal fa-eye` / `fas fa-eye`                         | `ph:eye-light`                     |
| `icon:eye-slash`       | `fal fa-eye-slash`                                  | `ph:eye-slash-light`               |
| `icon:lock`            | `fas fa-lock`                                       | `ph:lock-bold`                     |
| `icon:lock-open`       | `fal fa-lock-open`                                  | `ph:lock-open-light`               |
| `icon:unlock`          | `fas fa-unlock`                                     | `ph:lock-open-bold`                |
| `icon:check`           | `fal fa-check` / `fas fa-check`                     | `ph:check-bold`                    |
| `icon:check-circle`    | `fas fa-check-circle`                               | `ph:check-circle-bold`             |
| `icon:close`           | `fal fa-close` / `fal fa-times` / `fas fa-close`    | `ph:x-light`                       |
| `icon:xmark`           | `far fa-xmark-large` / `far fa-times`               | `ph:x-light`                       |
| `icon:ban`             | `fal fa-ban`                                        | `ph:prohibit-light`                |
| `icon:do-not-enter`    | `fal fa-do-not-enter`                               | `ph:prohibit-inset-light`          |
| `icon:warning`         | `fas fa-exclamation-triangle`                       | `ph:warning-bold`                  |
| `icon:question`        | `fal fa-question` / `fal fa-question-circle`        | `ph:question-light`                |
| `icon:skull`           | `fas fa-skull-crossbones`                           | `ph:skull-bold`                    |
| `icon:circle-down`     | `fal fa-circle-down`                                | `ph:arrow-circle-down-light`       |
| `icon:circle-up`       | `fal fa-circle-up`                                  | `ph:arrow-circle-up-light`         |
| `icon:circle-half`     | `fas fa-circle-half-stroke`                         | `ph:circle-half-bold`              |
| `icon:down-to-bracket` | `fal fa-down-to-bracket`                            | `ph:arrow-line-down-light`         |
| `icon:percent`         | `fal fa-percent`                                    | `ph:percent-light`                 |
| `icon:clock`           | `fal fa-clock` / `fas fa-clock`                     | `ph:clock-light`                   |
| `icon:timeline`        | `fal fa-timeline`                                   | `ph:line-segments-light`           |
| `icon:file`            | `fal fa-file`                                       | `ph:file-light`                    |
| `icon:file-alt`        | `fas fa-file-alt`                                   | `ph:file-text-light`               |
| `icon:file-code`       | `fas fa-file-code`                                  | `ph:file-code-light`               |
| `icon:folder`          | `fas fa-folder-open`                                | `ph:folder-open-light`             |
| `icon:list`            | `fas fa-list`                                       | `ph:list-bold`                     |
| `icon:ballot`          | `fal fa-ballot-check`                               | `ph:clipboard-text-light`          |
| `icon:memo-check`      | `fal fa-memo-circle-check`                          | `ph:note-pencil-light`             |
| `icon:chart-pie`       | `fas fa-chart-pie`                                  | `ph:chart-pie-light`               |
| `icon:project`         | `fas fa-project-diagram`                            | `ph:graph-light`                   |
| `icon:inbox`           | `fas fa-inbox`                                      | `ph:tray-arrow-down-light`         |
| `icon:comments`        | `fas fa-comments`                                   | `ph:chats-light`                   |
| `icon:comment`         | `fas fa-comment`                                    | `ph:chat-light`                    |
| `icon:binoculars`      | `fal fa-binoculars`                                 | `ph:binoculars-light`              |
| `icon:ellipsis`        | `fas fa-ellipsis-v`                                 | `ph:dots-three-vertical-bold`      |
| `icon:shield-check`    | `fal fa-shield-check`                               | `ph:shield-check-light`            |
| `icon:user-check`      | `fas fa-user-check`                                 | `ph:user-check-bold`               |
| `icon:users`           | `fal fa-users` / `fas fa-users`                     | `ph:users-light`                   |
| `icon:users-lines`     | `fal fa-users-between-lines`                        | `ph:users-three-light`             |
| `icon:school`          | `fal fa-school`                                     | `ph:student-light`                 |
| `icon:chalkboard-user` | `fal fa-chalkboard-user`                            | `ph:chalkboard-teacher-light`      |
| `icon:graduation`      | `fas fa-graduation-cap`                             | `ph:graduation-cap-bold`           |
| `icon:door-open`       | `fas fa-door-open`                                  | `ph:door-open-bold`                |
| `icon:wrench`          | `fal fa-wrench` / `fas fa-wrench`                   | `ph:wrench-light`                  |
| `icon:cog`             | `fas fa-cog`                                        | `ph:gear-light`                    |
| `icon:cogs`            | `fal fa-cogs`                                       | `ph:gear-light`                    |
| `icon:gears`           | `fas fa-gears`                                      | `ph:gear-six-bold`                 |
| `icon:gear-complex`    | `fas fa-gear-complex`                               | `ph:gear-six-bold`                 |
| `icon:bug`             | `fal fa-bug`                                        | `ph:bug-light`                     |
| `icon:bullhorn`        | `fal fa-bullhorn`                                   | `ph:megaphone-light`               |
| `icon:bell`            | `fal fa-bell`                                       | `ph:bell-light`                    |
| `icon:envelope`        | `fal fa-envelope`                                   | `ph:envelope-light`                |
| `icon:github`          | `fa-brands fa-github`                               | `simple-icons:github`              |
| `icon:twitter`         | `fa-brands fa-twitter`                              | `simple-icons:twitter`             |

## Stratégie de migration progressive

### Étape 1 : nouveaux templates → utiliser directement les alias

```twig
<twig:UX:Icon name="icon:save" class="w-4 h-4" />
```

### Étape 2 : templates migrés → remplacer les `<i class="fa*">`

```bash
# Exemple de recherche pour migrer un template
grep -n 'class="fal fa-' templates/mon_template.html.twig
```

### Étape 3 : retirer FontAwesome Pro de legacy.scss quand 0 occurrences restantes

```scss
// Supprimer ces lignes de legacy.scss :
// @import "@fortawesome/fontawesome-pro/...";
```

## Ajouter un alias personnalisé

Dans `config/packages/ux_icons.yaml` :

```yaml
ux_icons:
  aliases:
    icon:mon-icone: 'ph:mon-icone-light'
```

