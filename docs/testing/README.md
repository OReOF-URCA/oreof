# Tests PHP — ORéOF v2

Quand lire : écrire ou lancer des tests PHPUnit.
À mettre à jour si : `tests/Support/`, `tests/Fixtures/`, `tests/Smoke/`, `src/DataFixtures/Test/`, `.env.test`, arborescence `tests/`, `phpunit.xml.dist`, scripts composer (`test`, `analyse`, `check`), `phpstan.dist.neon`, `phpstan-baseline.neon`, `config/packages/reprise.yaml`, cibles `test*` du `../Makefile`, `.github/workflows/`.

## Lancer

Docker requis ; le Makefile est dans `../` et cible v1 par défaut → toujours `APP=v2`.

| Besoin | Commande |
|---|---|
| Toute la suite | `make test APP=v2` |
| Avec couverture (xdebug) | `make test-coverage APP=v2` (ajouter `--coverage-html var/coverage` en CLI pour un rapport HTML ; aucun rapport n'est configuré dans `phpunit.xml.dist`) |
| Ciblé | `make cli APP=v2` puis `php bin/phpunit tests/Unit/Service/XTest.php` |
| Options utiles | `--testdox`, `--stop-on-failure`, `--filter testNom` |

Deux suites (`phpunit.xml.dist`) : `Smoke` (`tests/Smoke`) et `Project` (`tests/` hors Smoke +
`packages/workflow-operations-bundle/tests`). `vendor/bin/phpunit` lance les deux ; composer : `test`, `test:smoke`,
`analyse`, `lint:container`, `check` (conteneur + PHPStan + tests). L'environnement de test lit `.env.test`
(`APP_SECRET`, `MAILER_DSN=null://null`, `MESSENGER_TRANSPORT_DSN=sync://`) ; `DATABASE_URL` de l'environnement l'emporte.

## Organisation

| Dossier | Contenu | Base |
|---|---|---|
| `tests/Unit/` (`Service`, `DTO`, `Navigation`…) | logique pure, dépendances mockées, sans BD | `PHPUnit\Framework\TestCase` |
| `tests/Integration/` (`Doctrine`, `Workflow`) | persistance, relations, transitions | `App\Tests\Support\TestCase` + traits |
| `tests/Functional/` (`Controller`, `Security`) | routes HTTP, droits, CSRF | `WebTestCase` |
| `tests/Workflow/` | configuration et formulaires des workflows | `PHPUnit` ou `KernelTestCase` |
| `tests/Smoke/` | `KernelBootTest` + `RouteSmokeTest` : appelle toutes les routes GET générables, échoue sur 404/5xx | `KernelTestCase` / `WebTestCase` |
| `tests/*.php` | tests historiques (`ParcoursCopyDataTest`, `VolumeHoraireParcoursTest`) | — |

Les fichiers `*ExampleTest.php` sont des **gabarits** (`markTestIncomplete('À impléter')`) : copier, renommer, adapter.

## Smoke test des routes

- Données : `src/DataFixtures/Test/FunctionalTestFixtures.php` (groupe `test`, utilisateur `admin-test`, campagne par
  défaut). `tests/Support/RouteParameterResolver.php` déduit les paramètres de route depuis les fixtures.
- Préparer la base : `APP_ENV=test php bin/console doctrine:schema:create` puis
  `APP_ENV=test php bin/console doctrine:fixtures:load --group=test`. Certaines routes GET modifient les données :
  **recharger les fixtures avant chaque exécution locale** (la CI repart d'une base vierge).
- `tests/Smoke/known-failures.txt` : routes déjà en échec (bugs réels ou limites des données de test : templates
  manquants, services externes, ids absents). Elles sont tolérées ; **toute autre route cassée fait échouer le test**.
  La liste ne doit que rétrécir : retirer la ligne dès qu'une route est corrigée.

## Helpers (`tests/Support/`, `tests/Fixtures/`)

- `TestCase` (extends `KernelTestCase`) : `getService(id)`, `createEntity(class, data)`.
- `DatabaseTrait` : `setUpDatabase()` ouvre une transaction, `tearDownDatabase()` rollback ; `persist()`,
  `persistAll()`, `refresh()`, `clearEntityManager()`.
- `EntityFixturesTrait` : `createMinimalParcours()`, `createMinimalFicheMatiere()`, `createMinimalDpeParcours()`
  (tableau d'overrides en paramètre).

## Règles

- Arrange / Act / Assert ; un test = un scénario, nom explicite (`testCreateVersionIncrementsBuild`, pas `testV1`).
- Tests indépendants (aucun ordre implicite) ; `setUp()` léger.
- Assertions précises (`assertSame`, `assertCount`) plutôt que `assertTrue($x)`.
- `declare(strict_types=1);`, namespace `App\Tests\<Dossier>`.

## Priorités de couverture (non encore implémentées)

1. `VersioningParcours` (unit + intégration), `SecureUploadService` (unit, sécurité), `TypeDiplomeResolver` (unit).
2. Entité `Parcours` (persistance), workflow `Parcours` (transitions), `FicheMatiere` (calculs ECTS/heures).
3. Fonctionnel : routes `/parcours`, exports PDF/Excel, authentification.

Objectifs : global 70 % min / 80 % cible ; `Service` et `TypeDiplome` 70/85 ; `Entity` 60/80 ; `DTO` 80/95 ;
`Controller` 40/60. 
## CI (GitHub Actions)

Vue d'ensemble, branches et déploiement : `docs/ops/ci-cd.md`.

| Workflow | Quand | Contenu |
|---|---|---|
| `ci.yml` | PR + push sur `v2`, `v2-dev`, `v2-dev-pol` | `composer audit`, lint YAML/conteneur (bloquants), lint Twig + ESLint (informatifs : dette existante), PHPStan (baseline), schéma + fixtures, PHPUnit (Project + Smoke) sur MariaDB 10.8, build Vite |
| `release-check.yml` | manuel, lundi 02h UTC, appelable | PHPUnit PHP 8.4 + 8.5 avec couverture, mapping Doctrine, PHPStan niveau 7 (informatif), build prod `--no-dev`, `npm audit` |

- **Baseline PHPStan** : `phpstan-baseline.neon` (niveau 6, ~1940 erreurs historiques). La CI refuse toute NOUVELLE
  erreur. Les erreurs corrigées mais encore listées sont tolérées (`reportUnmatchedIgnoredErrors: false`). Après avoir corrigé des erreurs : `php vendor/bin/phpstan analyse -c phpstan.dist.neon --generate-baseline=phpstan-baseline.neon`
  (la baseline ne doit que rétrécir). `phpstan.neon` local est ignoré par git : la CI utilise `phpstan.dist.neon`.
- En local, la base de test est `<base>_test` (suffixe Doctrine) : créer le schéma une fois avec
  `APP_ENV=test php bin/console doctrine:schema:create` (droits `CREATE` requis sur cette base).
- Reprise (`config/packages/reprise.yaml`, `when@test`) est en `strict_mode: false` : les tests n'exigent pas
  `public/build/entrypoints.json` (la CI PHP ne construit pas les assets).
- Les formulaires n'ont pas de CSRF en env `test` (`framework.form.csrf_protection` dans `when@test`).
- Le gabarit `ParcoursRepositoryExampleTest` et `ParcoursControllerExampleTest::testListParcoursPageIsSuccessful`
  sont marqués incomplets (fixtures obsolètes / authentification manquante).
