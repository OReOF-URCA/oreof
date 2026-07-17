# Quick Reference — Commandes de test

## 🚀 Lancer les tests

### Tous les tests
```bash
make test
```

### Avec rapport de couverture (HTML + Cobertura)
```bash
make test-coverage
# Rapport généré dans : var/coverage/
# Ouvrir : open var/coverage/index.html
```

### Par catégorie

```bash
make test-unit           # 🧬 Unitaires seulement (rapides)
make test-integration    # 🔗 Intégration (avec BD)
make test-functional     # 🌐 Fonctionnels (routes HTTP)
make test-watch          # 👁️  Mode watch (relance auto)
```

### Un test spécifique

```bash
# Dans le Docker :
make cli
php bin/phpunit tests/Unit/Service/MyTest.php
php bin/phpunit tests/Unit/ -v
php bin/phpunit tests/Integration/ --stop-on-failure
```

---

## 📊 Architecture des commandes

```
make test         ← Lance tous les tests dans Docker (accès BD)
├─ EXEC           = docker exec -ti ... oreof-web
├─ PHPUNIT        = ./vendor/bin/phpunit
└─ Résultat       = Tests avec accès à la BD du conteneur
```

Avantages :
- ✅ Accès à la **base de données** (transactions de test)
- ✅ Même **environnement que la prod**
- ✅ **xdebug disponible** pour couverture
- ✅ Tous les **services Symfony** accessibles

---

## 🎯 Workflow recommandé

```bash
# 1. Avant de committer
make test

# 2. Avant une PR, avec couverture
make test-coverage
open var/coverage/index.html  # Vérifier couverture

# 3. Pendant dev, tests rapides
make test-unit

# 4. Déboguer un test
make cli
php bin/phpunit tests/Unit/Service/MyTest.php -v
```

---

## 💡 Notes importantes

- **Tests lancés sur oreofv2 par défaut** (seule version avec tests)
- **Docker doit être up** (`make up` avant les tests)
- **Couverture** nécessite xdebug activé dans le conteneur
- **Rapports** générés dans `var/coverage/` (git-ignoré)

---

Besoin d'aide ? Consulte `docs/testing/GETTING_STARTED.md`
