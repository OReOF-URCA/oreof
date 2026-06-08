# AGENTS.md — ORéOF

## À lire en premier

- `docs/README.md` : index de la documentation interne.
- `docs/ops/command.md` : commandes utiles pour le développement et l’exploitation.
- `docs/index_ia.md` : **DÉPRÉCIÉ** — consulter directement `docs/ui-conventions/ui-conventions.md`,
  `docs/ui-conventions/icons-migration.md`, `docs/architecture/Update_BDD.md` et `docs/ops/command.md`.
- `docs/ui-conventions/README.md` : conventions d’interface utilisateur et migration Tailwind (sous-fichiers :
  `ui-conventions.md`, `icons-migration.md`, `bootstrap-to-tailwind-style-guide.md`).
- `docs/archives/` : documents **obsolètes** — ne jamais s'en servir pour générer du nouveau code.

## Vue d’ensemble

- Application Symfony 8/PHP 8.4+ orientée métier universitaire (maquettes, parcours, MCCC, validation, export).
- Les routes sont principalement en attributs dans `src/Controller/` (ex. `DefaultController` avec `#[Route]`).
- Les règles métier structurantes sont souvent dans des services/handlers dédiés plutôt que dans les contrôleurs.
- Frontend : trois entrées Webpack Encore — `assets/app.js` (principal), `assets/legacy.js` (Bootstrap/JS historique),
  `assets/print.js` (impression).

## Conventions d’architecture à respecter

- Les services sont autowirés/autoconfigurés depuis `src/` via `config/services.yaml` ; ajoutez des tags explicites quand un registre les consomme.
- Les handlers de type diplôme sont résolus par `App\TypeDiplome\TypeDiplomeResolver` via une clé dérivée de
  `TypeDiplome::libelleCourt` (code en majuscules). Les implémentations par diplôme sont dans
  `src/TypeDiplome/Diplomes/` (sous-dossiers : `But/`, `Daeu/`, `Licence/`, `M2E/`).
- Les workflows Symfony sont déclarés dans `config/packages/workflow.yaml` et pilotent aussi la UI via leurs `metadata` (boutons, icônes, formulaires, destinataires).
- Les uploads sensibles passent par `App\Service\SecureUploadService` ; ne contournez pas ses contrôles extension/MIME/taille.
- Gardez le vocabulaire métier en français et suivez les noms déjà présents (`Parcours`, `FicheMatiere`, `DpeParcours`, etc.).
- Les objets de transfert de données entre couches sont dans `src/DTO/` (ex. `StructureParcours`, `HeuresEctsFormation`,
  `WorkFlowData`).
- Le versioning des entités métier passe par les services dédiés `src/Service/Versioning*.php` (ex.
  `VersioningParcours`, `VersioningFicheMatiere`) ; ne dupliquez pas cette logique.
- Les traitements longs (génération PDF, exports) s'appuient sur Messenger ; des jobs Python sont lancés via
  `App\Service\PythonJobLauncher` et traités dans `python_worker/`.

## Fichiers repères à consulter avant de modifier

- `src/Command/McccPdfCommand.php` pour les exports PDF et les traitements par type de diplôme.
- `src/Service/SecureUploadService.php` pour la politique d’upload sécurisé.
- `src/Workflow/StepHandlerRegistry.php` et `config/packages/workflow.yaml` pour la mécanique de workflow.
- `src/TypeDiplome/TypeDiplomeResolver.php` et `config/services.yaml` pour l’ajout de nouveaux handlers.
- `src/Service/VersioningParcours.php` (et variantes) pour comprendre le système de versioning avant toute modification
  d’entité versionnable.
- `docs/architecture/maquette-modulaire.md` avant toute refonte de structure/validation/rendu.
- `docs/architecture/Update_BDD.md` avant toute modification de schéma de base de données ou d’entités Doctrine.

## Commandes utiles

- Développement Docker: `make up`, `make start`, `make open`, `make logs`, `make ps`, `make cli`.
- QA PHP: `make test`, `make test-coverage`, `make phpstan`.
- Frontend: `npm run dev`, `npm run watch`, `npm run build`, `npm run lint`.
- Base de données: `make import-db FILE=dump.sql DB=oreof_2026`, `make drop-db DB=nom_base`.
- Réinitialisation des mots de passe (dev) : `make reset-passwords` (positionne tous les mots de passe à `test`).
- Symfony dans le conteneur web: `php bin/console about`, `php bin/console doctrine:migrations:migrate -n`.

## Points d’attention

- `Makefile` expose `make cypress-open` / `make cypress-run` (appels `npm run cypress:open` / `cypress:run`), mais ces
  scripts ne sont **pas définis dans `package.json`** ; vérifiez avant de les utiliser.
- Les accès publics et la sécurité sont pilotés par `config/packages/security.yaml` ; attention aux routes d’export publiques.
- Messenger route `App\Message\Export` vers `async_export` (`config/packages/messenger.yaml`) : d’autres messages
  asynchrones existent (`ProcessGenerationJobMessage`, `RequestGenerationJobMessage`) ; surveillez les effets de bord
  asynchrones.
- Les assets sont gérés par Encore (`webpack.config.js`) avec `assets/app.js`, `assets/legacy.js` et `assets/print.js`;
  l’interface mélange encore Tailwind/Turbo et des fragments legacy Bootstrap, donc ne supposez pas une migration front
  100% uniforme.
