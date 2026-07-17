# Guide de démarrage — Testing ORéOF

## 🚀 Quick Start (5 min)

### 1. Vérifie que PHPUnit fonctionne
```bash
make test
# Ou directement :
php bin/phpunit tests/
```

### 2. Lance les tests existants
```bash
php bin/phpunit tests/ParcoursCopyDataTest.php --testdox
php bin/phpunit tests/VolumeHoraireParcoursTest.php --testdox
```

### 3. Explore la structure créée
```bash
tree tests/ -L 2
# Vous devriez voir :
# tests/
# ├── Unit/Service/
# ├── Unit/DTO/
# ├── Integration/Doctrine/
# ├── Integration/Workflow/
# ├── Functional/Controller/
# ├── Fixtures/
# ├── Support/
# └── bootstrap.php
```

## 📚 Structure des fichiers

### Support/ — Classes de base
- **TestCase.php** : Classe parente pour tous les tests (accès conteneur Symfony)
- **DatabaseTrait.php** : Helpers pour tests DB (persist, refresh, rollback)

### Fixtures/ — Données de test
- **EntityFixturesTrait.php** : Factories pour créer entités minimales rapidement

### Unit/ — Tests unitaires (sans DB)
```
Unit/
├── Service/        # Services isolés, logique métier
├── DTO/            # Validation DTO, transformation
└── Entity/         # Logique d'entité, getters/setters
```

**Exemple d'utilisation :**
```php
class MyServiceTest extends TestCase {
    public function testSomething() {
        $service = $this->getService(MyService::class);
        // Pas de DB, pas d'HTTP, juste la logique
    }
}
```

### Integration/ — Tests d'intégration (avec DB)
```
Integration/
├── Doctrine/       # Persistance, relations, requêtes
└── Workflow/       # Transitions, états, callbacks
```

**Exemple d'utilisation :**
```php
class RepositoryTest extends TestCase {
    use DatabaseTrait, EntityFixturesTrait;
    
    public function testPersist() {
        $entity = $this->createMinimalParcours(['libelle' => 'Test']);
        $this->persist($entity);
        // Entité persiste dans une transaction de test
    }
}
```

### Functional/ — Tests fonctionnels (HTTP)
```
Functional/
├── Controller/     # Routes, réponses HTTP
└── Security/       # Auth, permissions, CSRF
```

**Exemple d'utilisation :**
```php
class ControllerTest extends WebTestCase {
    public function testRoute() {
        $client = static::createClient();
        $client->request('GET', '/parcours');
        $this->assertResponseIsSuccessful();
    }
}
```

## 🎯 Workflow recommandé

### Pour un **service métier** (ex: VersioningParcours)
1. Crée `tests/Unit/Service/VersioningParcoursTest.php`
2. Hérite de `TestCase`
3. Teste la logique avec mocks
4. Pas d'accès DB

### Pour une **entité** (ex: Parcours)
1. Crée `tests/Integration/Doctrine/ParcoursTest.php`
2. Hérite de `TestCase` + `DatabaseTrait` + `EntityFixturesTrait`
3. Teste la persistance et les relations
4. Accès DB en transaction

### Pour une **route** (ex: POST /parcours)
1. Crée `tests/Functional/Controller/ParcoursControllerTest.php`
2. Hérite de `WebTestCase`
3. Teste la réponse HTTP et les redirections
4. Client HTTP complet

## 💡 Bonnes pratiques

### ✅ À faire
```php
// 1. Arrange, Act, Assert
public function testSomething() {
    // Arrange — setup
    $parcours = $this->createMinimalParcours(['libelle' => 'Test']);
    
    // Act — exécute
    $result = $this->someService->process($parcours);
    
    // Assert — vérifie
    $this->assertTrue($result);
}

// 2. Un test = un scénario
public function testCreatesNewVersionWhenModified() { ... }
public function testKeepsVersionWhenNoChange() { ... }

// 3. Utilise les fixtures
$parcours = $this->createMinimalParcours(['libelle' => 'Custom']);

// 4. Nomme clairement
// ✅ testCreateVersionIncrementsBuild
// ❌ testV1
```

### ❌ À ne pas faire
```php
// ❌ Trop de logique dans setUp()
protected function setUp() {
    // Crée 100 entités...
}

// ❌ Dépendances entre tests
public function testA() { ... }
public function testB() { ... } // Dépend de testA

// ❌ Assertions floues
$this->assertTrue($something); // Trop vague

// ✅ Plutôt :
$this->assertEquals('expected', $something);
$this->assertCount(3, $items);
$this->assertContains('value', $array);
```

## 🔧 Commandes utiles

```bash
# Lancer tous les tests
make test

# Lancer unit tests seulement
php bin/phpunit tests/Unit/

# Lancer integration tests seulement
php bin/phpunit tests/Integration/

# Lancer un test spécifique
php bin/phpunit tests/Unit/Service/VersioningParcoursTest.php

# Format lisible (testdox)
php bin/phpunit --testdox

# Rapport de couverture (HTML)
php bin/phpunit --coverage-html=coverage/

# S'arrête au premier échec
php bin/phpunit --stop-on-failure

# Verbose (affiche assertions)
php bin/phpunit -v
```

## 📊 Mesurer la couverture

```bash
php bin/phpunit --coverage-html=coverage/

# Ouvre dans le navigateur
open coverage/index.html
```

## 🐛 Déboguer un test

```bash
# Affiche la trace complète
php bin/phpunit tests/MyTest.php -v

# Ajoute des echo/var_dump
public function test() {
    $result = $this->service->process();
    var_dump($result);
    $this->assertTrue($result);
}

# Ou utilise xdebug si configuré
```

## 📖 Fichiers modèles à adapter

Tous les fichiers dans `tests/Unit/`, `tests/Integration/`, `tests/Functional/` contiennent des commentaires `// À impléter` pour te guider.

Remplace-les par ta logique réelle en t'inspirant des patterns montrés.

---

**Prochaine étape ?** Choisis un service critique (ex: `VersioningParcours`) et implémente un vrai test dedans ! 🎯
