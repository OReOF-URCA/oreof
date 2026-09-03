# 🧪 Testing Setup — ORéOF v2

## ✅ Installation complétée !

Votre infrastructure de test est **100% opérationnelle**. Voici ce qui a été mis en place :

---

## 📁 Structure créée

```
tests/
├── Support/                          # 🔧 Classes de base
│   ├── TestCase.php                 # Classe parente (accès Symfony)
│   └── DatabaseTrait.php            # Helpers DB (persist, refresh, etc.)
│
├── Fixtures/                         # 📦 Données de test
│   └── EntityFixturesTrait.php      # Factories pour créer entités rapidement
│
├── Unit/                             # 🧬 Tests unitaires (sans DB)
│   ├── Service/
│   │   ├── VersioningParcoursExampleTest.php
│   │   └── SecureUploadServiceExampleTest.php
│   ├── DTO/
│   │   └── StructureParcoursExampleTest.php
│   └── Entity/
│
├── Integration/                      # 🔗 Tests d'intégration (avec DB)
│   ├── Doctrine/
│   │   └── ParcoursRepositoryExampleTest.php
│   └── Workflow/
│       └── WorkflowTransitionExampleTest.php
│
├── Functional/                       # 🌐 Tests fonctionnels (HTTP)
│   ├── Controller/
│   │   └── ParcoursControllerExampleTest.php
│   └── Security/
│
├── bootstrap.php                     # ✅ Existant
├── ParcoursCopyDataTest.php         # ✅ Existant
└── VolumeHoraireParcoursTest.php    # ✅ Existant
```

---

## 📚 Documentation

### 🚀 Pour commencer
👉 **[GETTING_STARTED.md](GETTING_STARTED.md)** — Guide complet d'utilisation + commandes

### 📋 Stratégie globale
👉 **[STRATEGY.md](STRATEGY.md)** — Services à tester en priorité + couverture + templates

---

## 🎯 Prochaines étapes

### Phase 3 — Implémenter les tests réels

Choisissez un service et commencez :

**Option 1 : Service simple (recommandé pour débuter)**
```bash
# Copie le template
cp tests/Unit/Service/SecureUploadServiceExampleTest.php \
   tests/Unit/Service/SecureUploadServiceTest.php

# Adapter le fichier avec votre logique réelle
# Lance les tests
php bin/phpunit tests/Unit/Service/SecureUploadServiceTest.php
```

**Option 2 : Entité avec persistance**
```bash
# Copie le template
cp tests/Integration/Doctrine/ParcoursRepositoryExampleTest.php \
   tests/Integration/Doctrine/ParcoursTest.php

# Adapter et lancer
php bin/phpunit tests/Integration/Doctrine/ParcoursTest.php
```

### Phase 4 — Intégrer CI/CD

```bash
# Vérifier que make test fonctionne
make test

# Ajouter à GitHub Actions si absent
# .github/workflows/test.yml
```

### Phase 5 — Mesurez la couverture

```bash
# Générer rapport HTML
php bin/phpunit --coverage-html=coverage/

# Ouvrir dans le navigateur
open coverage/index.html
```

---

## 🔧 Commandes rapides

```bash
# ✅ Lancer tous les tests
make test

# 🧬 Unitaires seulement
php bin/phpunit tests/Unit/

# 🔗 Intégration seulement
php bin/phpunit tests/Integration/

# 🌐 Fonctionnels seulement
php bin/phpunit tests/Functional/

# 📊 Format lisible
php bin/phpunit --testdox

# 🎯 S'arrêter au premier échec
php bin/phpunit --stop-on-failure

# 📈 Rapport couverture
php bin/phpunit --coverage-html=coverage/

# 🔍 Un test spécifique
php bin/phpunit tests/Unit/Service/MyTest.php
```

---

## 💡 Quick Tips

### 1️⃣ Tester un **service métier**
```php
class MyServiceTest extends TestCase {
    public function testLogique() {
        $service = $this->getService(MyService::class);
        // Pas de DB, juste la logique métier
    }
}
```

### 2️⃣ Tester une **entité + persistance**
```php
class MyEntityTest extends TestCase {
    use DatabaseTrait, EntityFixturesTrait;
    
    public function testPersist() {
        $entity = $this->createMinimalParcours();
        $this->persist($entity);
        // Entité persiste en transaction de test
    }
}
```

### 3️⃣ Tester une **route HTTP**
```php
class MyControllerTest extends WebTestCase {
    public function testRoute() {
        $client = static::createClient();
        $client->request('GET', '/parcours');
        $this->assertResponseIsSuccessful();
    }
}
```

---

## 📊 Objectifs de couverture

| Zone | Minimum | Objectif |
|------|---------|----------|
| Services | 70% | 85% |
| Entités | 60% | 80% |
| DTO | 80% | 95% |
| Type Diplôme | 70% | 85% |
| **GLOBAL** | **70%** | **80%** |

---

## 📖 Fichiers de référence

- **Base de tous les tests** → `tests/Support/TestCase.php`
- **Pour accès BD** → `tests/Support/DatabaseTrait.php` + `tests/Fixtures/EntityFixturesTrait.php`
- **Templates d'exemple** → `tests/Unit/Service/*ExampleTest.php`, etc.

---

## ❓ Questions ?

- 📖 Lire `docs/testing/GETTING_STARTED.md`
- 📋 Consulter `docs/testing/STRATEGY.md`
- 🔍 Explorer les fichiers `*ExampleTest.php` pour patterns
- 🧪 Lancer un test avec `-v` pour debug

---

**Bon testing ! 🚀**
