# Stratégie de testing — ORéOF

## 📋 Checklist de mise en place

### Phase 1 : Structure ✅ DONE
- [x] Créer structure `tests/Unit/`, `Integration/`, `Functional/`
- [x] Créer `Support/TestCase.php` (classe parente)
- [x] Créer `Support/DatabaseTrait.php` (helpers DB)
- [x] Créer `Fixtures/EntityFixturesTrait.php` (factories)

### Phase 2 : Templates ✅ DONE
- [x] Template test unitaire : `Unit/Service/VersioningParcoursExampleTest.php`
- [x] Template test unitaire : `Unit/Service/SecureUploadServiceExampleTest.php`
- [x] Template test DTO : `Unit/DTO/StructureParcoursExampleTest.php`
- [x] Template intégration : `Integration/Doctrine/ParcoursRepositoryExampleTest.php`
- [x] Template workflow : `Integration/Workflow/WorkflowTransitionExampleTest.php`
- [x] Template fonctionnel : `Functional/Controller/ParcoursControllerExampleTest.php`

### Phase 3 : Tests critiques — À faire
- [ ] Implémenter `VersioningParcours` tests réels
- [ ] Implémenter `SecureUploadService` tests réels
- [ ] Implémenter `TypeDiplomeResolver` tests réels
- [ ] Implémenter persistance `Parcours` tests
- [ ] Implémenter workflow `Parcours` tests

### Phase 4 : CI/CD — À faire
- [ ] Ajouter `phpunit` au Makefile si absent
- [ ] Configurer GitHub Actions `.github/workflows/test.yml`
- [ ] Définir seuil de couverture minimum (70%)
- [ ] Ajouter badge couverture au README

### Phase 5 : Documentation — À faire
- [ ] Terminer `docs/testing/GETTING_STARTED.md` (✅ Done)
- [ ] Créer `docs/testing/BEST_PRACTICES.md`
- [ ] Créer `docs/testing/PATTERNS.md`

---

## 🎯 Services métier à tester en priorité

### CRITIQUE (à faire en premier)

1. **`VersioningParcours`** — Logique de versioning + historique
   - Type : Unitaire + Intégration
   - Complexité : Moyenne
   - Impact : Haut

2. **`SecureUploadService`** — Validation upload (taille, extension, MIME)
   - Type : Unitaire
   - Complexité : Faible
   - Impact : Critique (sécurité)

3. **`TypeDiplomeResolver`** — Résolution des handlers par diplôme
   - Type : Unitaire
   - Complexité : Faible
   - Impact : Haut

### IMPORTANT (2ème vague)

4. **Entité `Parcours`** — Persistance, relations, validations
   - Type : Intégration
   - Complexité : Moyenne
   - Impact : Haut

5. **Workflow `Parcours`** — Transitions d'état
   - Type : Intégration
   - Complexité : Moyenne
   - Impact : Haut

6. **Entité `FicheMatiere`** — Calculs ECTS/heures
   - Type : Unitaire + Intégration
   - Complexité : Faible
   - Impact : Moyen

### COUVERTURE FUTURE (3ème vague)

7. **Routes `/parcours`** — Fonctionnel HTTP
8. **Routes exports PDF/Excel** — Fonctionnel HTTP
9. **Routes authentification** — Sécurité

---

## 📊 Objectifs de couverture

| Catégorie | Minimum | Objectif | Critique |
|-----------|---------|----------|----------|
| `src/Service/*` | 70% | 85% | ✅ |
| `src/Entity/*` | 60% | 80% | ✅ |
| `src/DTO/*` | 80% | 95% | ✅ |
| `src/TypeDiplome/*` | 70% | 85% | ✅ |
| `src/Controller/*` | 40% | 60% | ⚠️ |
| **GLOBAL** | **70%** | **80%** | ✅ |

---

## 🔧 Commandes recommandées

```bash
# Phase 3 : Vérifier couverture progressivement
make test
php bin/phpunit tests/Unit/ --coverage-text
php bin/phpunit tests/Integration/ --coverage-text

# Phase 4 : Générer rapport HTML
php bin/phpunit --coverage-html=coverage/
open coverage/index.html

# Avant chaque commit
php bin/phpunit --testdox --stop-on-failure

# Vérification continue
php bin/phpunit tests/ --watch  # Si disponible
```

---

## 📝 Template pour démarrer un test réel

Copie ce template et adapte-le :

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\MyService;
use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    private MyService $service;

    protected function setUp(): void
    {
        // Initialiser le service
        $this->service = new MyService(/* dépendances mockées */);
    }

    /**
     * ✅ Scénario : Happy path
     */
    public function testHappyPath(): void
    {
        // Arrange
        $input = ['key' => 'value'];

        // Act
        $result = $this->service->process($input);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('expected', $result);
    }

    /**
     * ❌ Scénario : Error handling
     */
    public function testErrorHandling(): void
    {
        // Arrange
        $invalidInput = [];

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->service->process($invalidInput);
    }
}
```

---

## 🚀 Comment commencer

1. **Choisis un service simple** (ex: `SecureUploadService`)
2. **Copie le template au-dessus**
3. **Adapte-le à ta classe réelle**
4. **Implémente les tests**
5. **Lance : `php bin/phpunit tests/Unit/Service/SecureUploadServiceTest.php`**
6. **Fais passer les tests ✅**
7. **Répète pour les autres services**

---

## 📚 Ressources

- **PHPUnit Docs** : https://phpunit.readthedocs.io/
- **Symfony Testing** : https://symfony.com/doc/current/testing.html
- **AAA Pattern** : Arrange, Act, Assert (structure des tests)
- **TDD** : Test-Driven Development (écrire les tests avant le code)

---

**Questions ?** Consulte `GETTING_STARTED.md` ou les fichiers exemples dans `tests/`.
