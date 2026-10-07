# AGENTS.md — ORéOF v2

Point d'entrée unique pour les agents IA (Claude, Copilot, Codex…). Lire ce fichier, puis **uniquement** la doc
correspondant à la tâche (table « Routage »). Ne jamais s'appuyer sur `docs/archives/` (obsolète). Ne pas lire
`CHANGELOG.md` (≈ 420 Ko, généré par release-please) ; utiliser `git log` si besoin d'historique.

## Projet

Symfony 8 / PHP 8.4+, Doctrine, Twig + Twig Components, Symfony UX (Turbo, Stimulus, Live, Autocomplete/Tom Select,
Icons, Chart.js). Métier universitaire : offre de formation, maquettes, parcours, MCCC, workflows de validation, exports.

- Front :  Vite (Symfony Reprise), entrées `assets/app.js` (charge aussi Bootstrap JS pour le legacy, Trix,
  DataTables) et `assets/print.js`. Tailwind v4 dans `assets/styles/app.css`. Migration Bootstrap → Tailwind
  **en cours** : l'UI est mixte.
- Routes en attributs dans `src/Controller/`. Logique métier dans services/handlers, pas dans les contrôleurs.
- Vocabulaire métier en français, réutiliser les noms existants (`Parcours`, `FicheMatiere`, `DpeParcours`, `Ue`,
  `ElementConstitutif`…).

## Points d'architecture (vérifier avant de modifier)

| Sujet | Où regarder | Règle |
|---|---|---|
| Services | `config/services.yaml` | autowire/autoconfigure depuis `src/` ; tag explicite si un registre les consomme |
| Type de diplôme | `src/TypeDiplome/TypeDiplomeResolver.php`, `src/TypeDiplome/Diplomes/{But,Daeu,Licence,M2E}/` | clé = `TypeDiplome::libelleCourt` en majuscules |
| Workflows | `config/packages/workflow.yaml`, `src/Workflow/StepHandlerRegistry.php` | les `metadata` pilotent aussi l'UI (boutons, icônes, formulaires, destinataires) |
| Versioning | `src/Service/Versioning*.php` | ne jamais dupliquer cette logique |
| Uploads | `src/Service/SecureUploadService.php` | ne pas contourner les contrôles extension/MIME/taille |
| DTO | `src/DTO/` | `StructureParcours`, `HeuresEctsFormation`, `WorkFlowData`… |
| Async | `config/packages/messenger.yaml`, `App\Service\PythonJobLauncher`, `python_worker/` | transports `async_export`, `async_email`, `async_mccc_backup` (+ jobs `ProcessGenerationJobMessage`/`RequestGenerationJobMessage`) ; attention aux effets de bord |
| Sécurité | `config/packages/security.yaml` | attention aux routes d'export publiques |
| Navigation | `src/Navigation/` | le menu est la source unique (topbar, pages de section, breadcrumbs) |
| Schéma BDD | `migrations/`, `src/Command/MigrateV2Command.php` | voir `docs/architecture/migration-v2.md` |

## Routage : quelle doc lire

| Tâche | Lire |
|---|---|
| Template Twig, CSS, composant UI, migration Bootstrap | `docs/ui-conventions/ui-conventions.md` |
| Icônes (`fa-*` → `icon:*`) | `docs/ui-conventions/icones.md` |
| Menu, section, breadcrumb | `docs/composants/navigation.md` |
| Aides contextuelles, FAQ, import/export des aides | `docs/composants/aides-faq.md` |
| Recherche plein texte (`/recherche/parcours`, moteur fuzzy) | `docs/composants/recherche.md` |
| Champs JSON configurables (`JsonConfigType`, `DynamicFieldsType`) | `docs/formulaires/README.md` |
| Entité, colonne, migration, bascule V2 | `docs/architecture/migration-v2.md` |
| Refonte structure/validation/rendu de maquette | `docs/architecture/maquette-modulaire.md` |
| Écrire/lancer des tests | `docs/testing/README.md` |
| Commande `app:*` | `docs/ops/command.md` |
| Installation, déploiement | `docs/ops/install.md` |
| CI/CD, workflows GitHub, release, hotfix | `docs/ops/ci-cd.md` |

Index complet : `docs/README.md`.

## Règles de contribution

- Diffs minimaux ; pas de reformatage massif ni de refactor global non demandé.
- Préserver les hooks : `id`, `data-*`, `stimulus_controller/action/target()`, `aria-*`.
- Réutiliser un composant existant plutôt que dupliquer du markup ; nouveau composant = classe dans
  `src/Twig/Components/` + template dans `templates/components/`.
- Nouveau HTML : préférer un Twig Component à un filtre Twig qui renvoie du HTML.
- Turbo : une vue partielle peut être rendue en page, `turbo-frame` ou `turbo-stream` → renvoyer le bon wrapper
  (éviter `content-missing`), fragments autonomes.
- Pas de nouvelle pile JS si Stimulus suffit. Pas de logique métier côté front.
- Ne pas supprimer une classe legacy sans vérifier ses usages JS/Twig/CSS.

## Maintenance de la documentation (obligatoire)

Chaque doc indique en tête « À mettre à jour si : … » (code dont elle dépend). Si ton changement touche ce code
(ajout/renommage/suppression de composant, prop, commande, option, alias d'icône, étape de migration, helper de test,
cible Makefile, chemin cité…), **mets à jour la doc concernée dans le même commit/PR**. Idem pour ce fichier si la stack,
l'architecture ou une règle change. Nouveau sujet → nouveau fichier + entrée dans `docs/README.md` et dans la table
« Routage » ci-dessus. Doc devenue fausse ou sans objet → corriger ou déplacer dans `docs/archives/`. Signaler en fin de
tâche les docs mises à jour (ou pourquoi aucune ne l'a été).

## Commandes

Makefile dans le dossier parent (`../Makefile`). **`APP` vaut `v1` par défaut → toujours passer `APP=v2`** pour ce
dépôt (conteneur `oreof-web-v2`, base `oreof_v2`, http://localhost:8821). Docker requis.

- Docker : `make up|start|stop|restart|logs|ps|cli|open APP=v2`
- QA : `make test|test-coverage|phpstan APP=v2` ; front : `npm run dev|watch|build|lint`
- BDD : `make import-db FILE=dump.sql APP=v2 [DB=nom]`, `make drop-db APP=v2 [DB=nom]`, `make reset-passwords APP=v2`
  (mdp = `test`)
- Dans le conteneur (`make cli APP=v2`) : `php bin/console about`, `php bin/console doctrine:migrations:migrate -n`

## Validation avant de conclure

`npm run dev` (+ `npm run lint`) si front touché ; `make phpstan` et `make test` si PHP touché. Si Docker est
indisponible, le dire explicitement. `make cypress-*` appelle des scripts npm **absents** de `package.json`.
