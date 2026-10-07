# CI/CD — ORéOF v2

Quand lire : modifier la CI, préparer ou déployer une release, traiter un hotfix, préparer le serveur.
À mettre à jour si : `.github/workflows/`, `.github/dependabot.yml`, `phpstan.dist.neon`, `phpstan-baseline.neon`,
`release-please-config.json`, `config/packages/framework.yaml` (`when@test`), `tests/Smoke/`, tout futur dossier `deploy/`.

Légende de statut : **En place** = fonctionne dans le dépôt. **Cible** = décidé mais **non implémenté**.
**Bloqué** = attend un accès serveur ou un droit GitHub.

## Modèle de branches

| Branche | Rôle |
|---|---|
| `v2` | Branche principale V2 : cible des PR, base des releases (tags `vX.Y.Z` posés par release-please) |
| `v2-dev`, `v2-dev-pol` | Branches d'autres développeurs ; pas de branche de dev dédiée à ce stade. À conserver. Pas d'analyse préalable de leur contenu : les recoupements avec la CI se règlent à la fusion, la CI s'y applique |
| `main` | Lignée V1, divergente (voir `docs/architecture/migration-v2.md`) ; hors périmètre de cette CI |
| `feat/*`, `fix/*`, `ci/*` | Branches de travail, PR directement vers `v2` |

- Les messages de commit suivent Conventional Commits (`commitlint.config.js`). Le préfixe pilote la version
  (`fix:` = patch, `feat:` = minor) et, à terme, le type de déploiement.
- Hotfix : branche `fix/*` → PR vers `v2` → CI verte → merge. Pas de circuit séparé.

## Workflows en place

| Fichier | Déclencheur | Contenu |
|---|---|---|
| `ci.yml` | PR et push sur `v2`, `v2-dev`, `v2-dev-pol` ; appelable | `composer validate` + `composer audit`, lint YAML et conteneur (bloquants), lint Twig et ESLint (informatifs), PHPStan avec baseline, schéma + fixtures de test, PHPUnit (suites Project et Smoke) sur MariaDB 10.8, build Vite |
| `release-check.yml` | manuel, lundi 02h UTC, appelable | Vérification profonde, **hors hotfix** : PHPUnit PHP 8.4 et 8.5 avec couverture, mapping Doctrine, PHPStan niveau 7 (informatif), build `--no-dev` + `cache:warmup` prod, `npm audit` (informatif) |
| `release-please.yml` | push sur `v2` et `main` | PR de release, changelog, version dans `composer.json` |
| `dependabot.yml` | hebdomadaire (actions : mensuel) | PR composer, npm, github-actions vers `v2` |

Détails des tests et de la baseline : `docs/testing/README.md`.

Origine : la branche `test/php-test-stack` (PR #184, smoke tests des routes, fixtures, `.env.test`, scripts composer) a
été fusionnée dans `ci/cd`. Son workflow `php-tests.yml` est supprimé au profit de `ci.yml` (une seule CI, un seul
check requis). Version de MariaDB figée à **10.8** (celle de la stack ; ne pas la changer sans validation du lead dev).

### Baseline PHPStan

- `phpstan-baseline.neon` : 1941 erreurs historiques (niveau 6). La CI refuse toute **nouvelle** erreur.
- `reportUnmatchedIgnoredErrors: false` : corriger une erreur listée dans la baseline ne fait pas échouer la CI (pas de
  conflit sur ce fichier entre PR parallèles). Contrepartie : tant que la baseline n'est pas régénérée, le quota
  (`count`) d'une entrée reste inchangé, donc une erreur identique réintroduite passerait inaperçue.
- La baseline ne doit que rétrécir : la régénérer dans une PR dédiée, régulièrement, et non dans chaque PR de fonctionnalité :
  `php vendor/bin/phpstan analyse -c phpstan.dist.neon --generate-baseline=phpstan-baseline.neon`.
- La CI utilise `phpstan.dist.neon` ; `phpstan.neon` local est ignoré par git.

### Dette qui garde certaines étapes informatives

| Sujet | État | Pour rendre l'étape bloquante |
|---|---|---|
| Lint Twig | 4 templates en erreur (`badgeStep` inconnu dans `templates/parcours/_liste.html.twig`, 3 fichiers à balise vide) | corriger puis retirer `continue-on-error` dans `ci.yml` |
| ESLint | 96 erreurs (`no-undef`, `no-unused-vars`) | résorber puis retirer `continue-on-error` dans `ci.yml` |
| `npm audit` | `source-map-js` (high) | `npm audit fix`, puis retirer `continue-on-error` dans `release-check.yml` |
| Couverture | aucun seuil | mesurer le premier run de `release-check.yml`, fixer un seuil qui ne fait que monter |
| `composer.lock` périmé à chaque release | release-please écrit `version` dans `composer.json` sans mettre à jour le `content-hash` du lock : `composer validate` échoue après chaque release | rafraîchir avec `composer update --lock --no-install --no-scripts` (hash seul), ou automatiser dans `release-please.yml`, ou retirer `composer.json` des `extra-files` de `release-please-config.json` (la version affichée vient de `package.json`) |
| Smoke test des routes | 65 routes GET en échec toléré (`tests/Smoke/known-failures.txt`) : templates manquants (`communs/form_theme.html.twig`, `fiche_matiere/index.html.twig`), variables Twig absentes, services externes (Gotenberg, ACS) | corriger les routes et vider la liste |
| Tests | 20 incomplets, 1 ignoré (`ParcoursCopyDataTest` exige le parcours 405 de production) | écrire les tests prioritaires de `docs/testing/README.md` |

## Cible de déploiement (non implémenté)

### Trois niveaux, choisis automatiquement

| Type de release | Quand | Comportement |
|---|---|---|
| Patch (`fix:`) | immédiatement | bascule atomique, sans maintenance |
| Minor/major sans migration destructive | fenêtre calme calculée | bascule atomique, sans maintenance |
| Migration destructive ou rupture de workflow/formulaire | fenêtre calme | maintenance courte : 503 avec `Retry-After`, bandeau Mercure préalable |

Une migration est destructive si elle contient `DROP`, `RENAME` ou `NOT NULL` sans valeur par défaut.
Un contrôle automatique de ces motifs est à ajouter à `release-check.yml`.

### Heures creuses calculées

1. Un script sur le serveur (cron hebdomadaire) lit les logs d'accès des 28 derniers jours, calcule les 3 heures
   consécutives les plus calmes et écrit un fichier JSON.
2. Un workflow horaire déploie une release minor/major en attente si l'heure courante est dans la fenêtre **et** si le
   trafic des 10 dernières minutes est sous un seuil.
3. Un patch ignore la fenêtre.

### Déploiement quasi sans coupure

Le serveur de production est un Ubuntu classique, sans Docker. Principe : la CI construit l'archive
(`composer --no-dev`, `npm run build`, cache prod chauffé) ; le serveur ne compile rien.

```
/var/www/oreof/
  releases/<tag>/        code de la release
  shared/                var/log, .env.local, public/uploads, public/mccc-export, public/temp,
                         public/versioning, public/Docs-Offre, mccc-export, export, versioning_json
  current -> releases/<tag>
```

Séquence : dézip dans `releases/<tag>` → liens vers `shared/` → dump base → migrations → bascule atomique de
`current` → `reload` de php-fpm → `messenger:stop-workers` → health-check → rollback automatique si KO.

Conditions pour ne pas perturber les utilisateurs en cours de saisie :

| Condition | Règle |
|---|---|
| Sessions et CSRF | `session.save_path` hors du code ; `APP_SECRET` identique entre releases (`shared/.env.local` ou environnement système) |
| Migrations | exécutées avant la bascule, compatibles avec l'ancien code (ajouter d'abord, supprimer dans une release ultérieure) |
| Assets Vite | conserver les `public/build` des 2 dernières releases (sinon 404 sur les chunks chargés à la demande) |
| Dossiers inscriptibles | tous en `shared/`, sinon les fichiers disparaissent à la bascule |
| Opcache | document root sur `current/public`, `$realpath_root` côté Nginx, reload php-fpm à chaque bascule |
| Formulaires ouverts | ne pas renommer un état ou une transition de `config/packages/workflow.yaml` sans maintenance planifiée |

Limites : un seul serveur, donc pas de vraie haute disponibilité ; une migration lourde (`ALTER` sur grosse table) peut
verrouiller la table, à tester en pré-production sur une copie de la base.

## Liste de tâches

### Sans accès serveur

| # | Tâche | Statut |
|---|---|---|
| 1 | CI de base, baseline PHPStan, Dependabot, smoke tests des routes (fusion de la PR #184) | En place (branche `ci/cd`, PR à ouvrir vers `v2`, puis fermer la PR #184) |
| 2 | Première exécution réelle sur GitHub, corrections éventuelles | Fait (PR #220 verte : Node 24, healthcheck MariaDB, composer avant npm, `composer.lock`, Reprise `strict_mode: false` en test) |
| 3 | Protection de branche sur `v2` (CI verte obligatoire) | Bloqué : droits admin du dépôt |
| 4 | Workflow `commitlint` sur les PR | À faire |
| 5 | PAT ou GitHub App pour release-please (déclenche `release-check.yml` sur la PR de release) | Bloqué : droits admin |
| 6 | `build-release.yml` : archive sur tag `v*` (`--no-dev`, build Vite, cache prod) | À faire |
| 7 | Scripts `deploy/deploy.sh` et `rollback.sh`, testés sur dossier simulé | À faire |
| 8 | Classification de release (patch/minor/major, migrations destructives) | À faire |
| 9 | Script des heures creuses, testé sur logs de test | À faire |
| 10 | `deploy.yml` en `workflow_dispatch` et simulation uniquement | À faire |
| 11 | Route `/health` publique + test (`config/packages/security.yaml`) | À faire |
| 12 | Page de maintenance 503 et bandeau Mercure | À faire |
| 13 | `session.save_path` configurable par variable d'environnement | À faire |
| 14 | Tests prioritaires, seuil de couverture, contrôle « baseline qui ne grossit pas » | À faire |
| 15 | Résorber la dette (Twig, ESLint, `npm audit`) | À faire |
| 16 | Modèle de PR (migration destructive ? workflow modifié ?) | À faire |

### Avec le lead dev (accès serveur)

À obtenir ou à lui demander : utilisateur `deploy` avec clé SSH restreinte (prod **et** pré-prod), sudoers limité au
reload de php-fpm et des workers, chemin des logs d'accès, version de PHP, configuration Nginx/Apache du site,
`session.save_path`, emplacement du cache applicatif, gestion des workers (systemd ou supervisor), serveur joignable
depuis Internet (sinon runner GitHub auto-hébergé ou VPN), branche ou événement qui déploie la pré-prod (aucune branche
de dev dédiée à ce jour : soit chaque merge dans `v2`, soit une branche `preprod`).

## Voir aussi

- `docs/testing/README.md` : tests, helpers, détail de la CI.
- `docs/ops/install.md` : installation et déploiement manuel actuel.
- `docs/architecture/migration-v2.md` : migrations et bascule V1 → V2.
