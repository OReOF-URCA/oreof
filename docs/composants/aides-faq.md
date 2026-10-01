# Aides contextuelles, FAQ et galerie d'images

Quand lire : modification de l'administration des aides / de la FAQ, de l'éditeur Markdown, ou transfert des aides entre
instances (local → pré-production → production).
À mettre à jour si : `src/Service/HelpTransferService.php`, `src/Controller/Help*Controller.php`,
`src/Controller/Faq*Controller.php`, `src/Twig/HelpExtension.php`, `templates/help_admin/`, `templates/faq_admin/`,
`templates/components/{help_editor,faq_editor,help_image_manager,markdown_toolbar}.html.twig`,
`public/js/editor_helpers.js`, `src/Command/Migration/V2HelpSeedData.php`.

## Où regarder

| Sujet | Code |
|---|---|
| Entités | `Help` (clé fonctionnelle `routeSlug`), `Faq` (`question`, `ordre`), `HelpImage` (`fichier` dans `public/uploads/help_images/`) |
| Admin aides / FAQ | `HelpAdminController` (`/administration/help`), `FaqAdminController` (`/administration/faq`), `ROLE_ADMIN` |
| Galerie d'images | `HelpImageController` (JSON) + composant `help_image_manager` ; upload via `SecureUploadService` (contexte `help_images`) |
| Éditeur | Live Components `help_editor` / `faq_editor` + `markdown_toolbar` + `public/js/editor_helpers.js` |
| Rendu Markdown | filtres Twig `help_markdown` puis `parse_embeds` (`HelpExtension`, CommonMark + tableaux + barré) |
| Affichage utilisateur | `templates/help/_content.html.twig` (panneau d'aide et page autonome), `templates/faq/index.html.twig` |
| Droits d'affichage | `HelpGrantService` (centres + rôle de la route cible) |

## Import / export entre instances

Page `/administration/help/transfert` (`HelpTransferController`, bouton « Import / export » des index aides et
FAQ). Logique dans `HelpTransferService` ; ne pas transférer les aides par dump SQL (les retours à la ligne du Markdown
y sont échappés en `\n` et se retrouvent littéraux s'ils sont recopiés dans du code).

- Archive ZIP : `manifest.json` (`format: oreof-help-export`, `version`, réglages de chaque élément) + `helps/NNN-<route>.md`
  + `faqs/NNN.md` (Markdown brut, relisible) + `images/<fichier>`. `?images=0` exporte sans la galerie.
- Rapprochement à l'import : aide par `routeSlug`, question par `question`, image par `fichier` (nom conservé pour que
  les liens `/uploads/help_images/...` du Markdown restent valides).
- Sans « Remplacer les éléments déjà présents » : seuls les éléments absents sont ajoutés. Avec : ils sont écrasés.
  Une image connue en base mais dont le fichier manque sur le serveur est toujours restaurée.
- Sécurité : `ROLE_ADMIN` + CSRF ; aucune extraction par chemin (entrées lues par nom validé) ; images déposées via
  `SecureUploadService::importStoredFile()` (mêmes contrôles taille/extension/MIME que l'upload) ; tailles plafonnées.
- Limite : l'archive doit tenir dans `upload_max_filesize` / `post_max_size` de PHP (affiché sur la page).
- Le bilan (compteurs + avertissements, ex. route inconnue sur l'instance cible) est affiché une fois après l'import.

## Pièges

- Aperçu de l'éditeur : rendu côté client par `marked` (`editor_helpers.js`), rendu final côté serveur par CommonMark.
  Garder les deux alignés (pas de `breaks`, tableaux et barré activés des deux côtés).
- Les scripts inline de ces pages sont réévalués à chaque visite Turbo : pas de `let`/`const` global, écouteurs
  `document` enregistrés une seule fois (garde-fous `window.__editorHelpersLoaded`, `window.__helpImageManagerBound`),
  initialisation sur `turbo:load` et non sur `DOMContentLoaded` seul.
- `V2HelpSeedData` : les contenus sont des chaînes PHP à quotes simples, `\n` y est littéral et converti par
  `decodeNewlines()` ; ne pas insérer ces chaînes telles quelles.
