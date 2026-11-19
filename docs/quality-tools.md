# Qualité du code — ORéOF V2

## Outils

- PHPStan : analyse statique déjà configurée (`composer analyse`).
- PHPUnit : tests existants (`composer test`).
- PHP-CS-Fixer : homogénéité du code (`make cs-check`, `make cs-fix`).
- Deptrac : règles de dépendances architecturales (`make architecture-check`).

## Installation (à effectuer avant activation en CI)

```bash
composer require --dev friendsofphp/php-cs-fixer deptrac/deptrac
```

Committer **composer.json et composer.lock ensemble**. Ne pas activer les contrôles CI avant la résolution des dépendances et une première exécution locale.

## Démarrage progressif

1. Lancer `make cs-check` pour inventorier les écarts de style.
2. Lancer `make architecture-check` pour inventorier les dépendances interdites.
3. Ajuster les couches Deptrac aux namespaces réellement utilisés.
4. Documenter les exceptions existantes et les réduire progressivement.
5. Ajouter les deux commandes au job Lint de GitHub Actions une fois la baseline stabilisée.

La configuration Deptrac initiale est **exploratoire** : les règles sur TypeDiplome et Workflow sont des hypothèses d'architecture, pas encore des garanties de non-régression. Elles nécessitent une revue des dépendances réelles avant activation bloquante.

Ne pas exécuter `make cs-fix` sur tout le dépôt dans une PR fonctionnelle : isoler les changements de formatage.
