# Installation ORéOF v2

Quand lire : installer un poste de dev, déployer en production, dépanner l'environnement.
À mettre à jour si : `../docker-compose.yml`, `../Makefile`, prérequis PHP/Node, variables d'environnement, transports Messenger.

## Développement (stack Docker `oreof-stack`)

Le dépôt applicatif vit dans `oreof-stack/oreofv2/` ; `docker-compose.yml`, `Makefile` et `docker/` sont dans le
dossier parent (détails : `../README.md`). Prérequis : Docker + Compose, GNU Make, Git, Node.js 20+.

| Service | Conteneur | Accès |
|---|---|---|
| ORéOF v2 (monte `./oreofv2` sur `/var/www/oreofv2`) | `oreof-web-v2` | http://localhost:8821 |
| ORéOF v1 | `oreof-web-v1` | http://localhost:8820 |
| MariaDB 10.8 (bases `oreof_v1`, `oreof_v2`) | `oreof-db` | — |
| phpMyAdmin | `oreof-adminsql` | http://localhost:9020 |
| Mercure | `oreof-mercure` | http://localhost:8070 |

Toutes les cibles `make` prennent **`APP=v2`** (défaut `v1`). Depuis `oreof-stack/` :

```bash
git clone https://github.com/OReOF-URCA/oreof.git oreofv2   # si absent
make up APP=v2 && make ps APP=v2
docker exec -ti -w /var/www/oreofv2 oreof-web-v2 composer install
cd oreofv2 && npm install && npm run dev                      # ou npm run watch
make import-db FILE=dump.sql APP=v2                           # optionnel : restaurer un dump
make cli APP=v2   # puis : php bin/console doctrine:migrations:migrate -n && php bin/console about
make reset-passwords APP=v2                                   # tous les mots de passe = "test"
```

`DATABASE_URL` est fourni par `docker-compose.yml` ; `.env.local` (non versionné) pour le reste : `APP_SECRET`,
`MERCURE_URL=http://mercure/.well-known/mercure`, `MERCURE_PUBLIC_URL=http://localhost:8070/.well-known/mercure`,
`MERCURE_JWT_SECRET`. Base issue de main : voir `docs/architecture/migration-v2.md`.

## Production

- Prérequis : PHP 8.4+ (`ctype`, `iconv`, `zip`, `intl`, `pdo_mysql`, `opcache`), MariaDB/MySQL, Composer 2,
  Node.js 20+, Nginx/Apache avec document root `public/`.
- Variables d'environnement système (jamais dans un `.env` versionné) : `APP_ENV=prod`, `APP_DEBUG=0`, `APP_SECRET`,
  `DATABASE_URL`, `MAILER_DSN`, `LDAP_*`, `MERCURE_*` selon usage.
- Écriture pour l'utilisateur web : `var/cache/`, `var/log/`, `var/sessions/`.

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci && npm run build
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod && php bin/console cache:warmup --env=prod
composer dump-env prod   # optionnel
# Worker Messenger supervisé (systemd/supervisor)
php bin/console messenger:consume async_export async_email async_mccc_backup --time-limit=3600 --memory-limit=256M --env=prod
```

Déploiement cible (releases + bascule atomique, heures creuses) : `docs/ops/ci-cd.md`. Déploiement manuel actuel : tag de release → dépendances + build → secrets → migrations → cache → redémarrage PHP-FPM/serveur web et
workers → contrôle de `var/log/` et de l'application.

## Dépannage

| Symptôme | Action |
|---|---|
| Conteneurs KO | `make logs APP=v2`, `make ps APP=v2` |
| Mémoire PHP | `../docker/php-conf.d/99-custom.ini` (`memory_limit`), puis `make restart APP=v2` |
| BD / migrations | vérifier `DATABASE_URL`, existence de la base et droits, relancer les migrations |
| Assets manquants | `npm run build` (ou `npm run dev`) |
