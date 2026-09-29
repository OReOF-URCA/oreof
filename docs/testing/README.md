# Tests PHP — ORéOF v2

Quand lire : écrire ou lancer des tests PHPUnit.
À mettre à jour si : `tests/Support/`, `tests/Fixtures/`, arborescence `tests/`, `phpunit.xml.dist`, cibles `test*` du `../Makefile`, CI.

## Lancer

Docker requis ; le Makefile est dans `../` et cible v1 par défaut → toujours `APP=v2`.

| Besoin | Commande |
|---|---|
| Toute la suite | `make test APP=v2` |
| Avec couverture (xdebug) | `make test-coverage APP=v2` (ajouter `--coverage-html var/coverage` en CLI pour un rapport HTML ; aucun rapport n'est configuré dans `phpunit.xml.dist`) |
| Ciblé | `make cli APP=v2` puis `php bin/phpunit tests/Unit/Service/XTest.php` |
| Options utiles | `--testdox`, `--stop-on-failure`, `--filter testNom` |

Suite unique « Project Test Suite » (`phpunit.xml.dist`) : `tests/` + `packages/workflow-operations-bundle/tests`.

## Organisation

| Dossier | Contenu | Base |
|---|---|---|
| `tests/Unit/` (`Service`, `DTO`, `Navigation`…) | logique pure, dépendances mockées, sans BD | `PHPUnit\Framework\TestCase` |
| `tests/Integration/` (`Doctrine`, `Workflow`) | persistance, relations, transitions | `App\Tests\Support\TestCase` + traits |
| `tests/Functional/` (`Controller`, `Security`) | routes HTTP, droits, CSRF | `WebTestCase` |
| `tests/Workflow/` | configuration et formulaires des workflows | `PHPUnit` ou `KernelTestCase` |
| `tests/*.php` | tests historiques (`ParcoursCopyDataTest`, `VolumeHoraireParcoursTest`) | — |

Les fichiers `*ExampleTest.php` sont des **gabarits** (`markTestIncomplete('À impléter')`) : copier, renommer, adapter.

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
`Controller` 40/60. Pas encore de CI de tests (`.github/workflows/` ne contient que `release-please.yml`).
