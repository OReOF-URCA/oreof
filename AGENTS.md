# AGENTS.md — ORéOF

## À lire en premier

- `docs/README.md` : index de la documentation interne.
- `docs/ops/command.md` : commandes utiles pour le développement et l’exploitation.
- `docs/index_ia.md` : index de documentation pour l’IA, à consulter impérativement avant toute proposition de code ou d’architecture.
- `docs/ui-conventions/README.md` : conventions d’interface utilisateur et migration Tail

## Vue d’ensemble

- Application Symfony 8/PHP 8.4+ orientée métier universitaire (maquettes, parcours, MCCC, validation, export).
- Les routes sont principalement en attributs dans `src/Controller/` (ex. `DefaultController` avec `#[Route]`).
- Les règles métier structurantes sont souvent dans des services/handlers dédiés plutôt que dans les contrôleurs.

## Conventions d’architecture à respecter

- Les services sont autowirés/autoconfigurés depuis `src/` via `config/services.yaml` ; ajoutez des tags explicites quand un registre les consomme.
- Les handlers de type diplôme sont résolus par `App\TypeDiplome\TypeDiplomeResolver` via une clé dérivée de `TypeDiplome::libelleCourt` (code en majuscules).
- Les workflows Symfony sont déclarés dans `config/packages/workflow.yaml` et pilotent aussi la UI via leurs `metadata` (boutons, icônes, formulaires, destinataires).
- Les uploads sensibles passent par `App\Service\SecureUploadService` ; ne contournez pas ses contrôles extension/MIME/taille.
- Gardez le vocabulaire métier en français et suivez les noms déjà présents (`Parcours`, `FicheMatiere`, `DpeParcours`, etc.).

## Fichiers repères à consulter avant de modifier

- `src/Command/McccPdfCommand.php` pour les exports PDF et les traitements par type de diplôme.
- `src/Service/SecureUploadService.php` pour la politique d’upload sécurisé.
- `src/Workflow/StepHandlerRegistry.php` et `config/packages/workflow.yaml` pour la mécanique de workflow.
- `src/TypeDiplome/TypeDiplomeResolver.php` et `config/services.yaml` pour l’ajout de nouveaux handlers.
- `docs/architecture/maquette-modulaire.md` avant toute refonte de structure/validation/rendu.

## Commandes utiles

- Développement Docker: `make up`, `make start`, `make open`, `make logs`, `make ps`, `make cli`.
- QA PHP: `make test`, `make test-coverage`, `make phpstan`.
- Frontend: `npm run dev`, `npm run watch`, `npm run build`, `npm run lint`.
- Base de données: `make import-db FILE=dump.sql DB=oreof_2026`.
- Symfony dans le conteneur web: `php bin/console about`, `php bin/console doctrine:migrations:migrate -n`.

## Points d’attention

- `Makefile` expose `make cypress-open` / `make cypress-run`, mais `package.json` ne définit pas encore ces scripts ; vérifiez avant de les utiliser.
- Les accès publics et la sécurité sont pilotés par `config/packages/security.yaml` ; attention aux routes d’export publiques.
- Messenger route `App\Message\Export` vers `async_export` (`config/packages/messenger.yaml`) : surveillez les effets de bord asynchrones.
- Les assets sont gérés par Encore (`webpack.config.js`) avec `assets/app.js` et `assets/print.js`; l’interface mélange encore Tailwind/Turbo et des fragments legacy Bootstrap, donc ne supposez pas une migration front 100% uniforme.
