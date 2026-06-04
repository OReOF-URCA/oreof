# Installation ORéOF

Ce document décrit une installation type d'ORéOF:

- en **développement** avec **Docker + Makefile** (recommandé pour l'équipe),
- en **production** (serveur classique PHP-FPM/Apache ou Nginx).

> Note: le dépôt est actuellement en Symfony 7.x. Les principes ci-dessous restent valables pour Symfony 7.4/8.

## 1) Prérequis

### Développement (Docker)

- Docker Engine + Docker Compose
- GNU Make
- Git
- Node.js 20+ et npm (pour les assets front)

### Production

- PHP 8.4+ avec extensions: `ctype`, `iconv`, `zip`, `intl`, `pdo_mysql`, `opcache`
- Base MariaDB/MySQL (ou autre SGBD supporté par votre configuration)
- Composer 2
- Node.js 20+ et npm
- Serveur web (Nginx ou Apache) pointant sur `public/`

---

## 2) Installation en développement (Docker + Makefile)

### 2.1. Récupérer le projet

```bash
git clone https://github.com/Dannebicque/oreof.git
cd oreof
```

### 2.2. Configurer l'environnement local

Créez/ajustez votre fichier `.env.local` (non versionné):

```dotenv
APP_ENV=dev
APP_SECRET=change-me

# Exemple Docker local (conteneur db)
DATABASE_URL="mysql://oreof:PASSWORD@oreof-db:3306/oreof?serverVersion=10.8&charset=utf8mb4"

# Mercure (si utilisé)
MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:8070/.well-known/mercure
MERCURE_JWT_SECRET="!ChangeThisMercureHubJWTSecretKey!"
```

> Adaptez le nom de base (`oreof`, `oreof_2026`, etc.) selon votre dump local.

### 2.3. Démarrer les conteneurs

```bash
make up
make ps
```

Accès usuels:

- App: `http://localhost:8820`
- phpMyAdmin: `http://localhost:9020`

### 2.4. Installer les dépendances backend/frontend

```bash
docker exec -ti -w /var/www/oreof oreof-web composer install
npm install
```

### 2.5. Initialiser la base de données

```bash
docker exec -ti -w /var/www/oreof oreof-web php bin/console doctrine:migrations:migrate -n
```

Si vous partez d'un dump SQL:

```bash
docker exec -i oreof-db sh -c 'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < dump.sql
```

### 2.6. Compiler les assets

```bash
npm run dev
```

En mode watch pendant le développement:

```bash
npm run watch
```

### 2.7. Vérifier le fonctionnement

```bash
docker exec -ti -w /var/www/oreof oreof-web php bin/console about
make logs
```

Commandes utiles du `Makefile`:

```bash
make start
make open
make stop
make restart
make cli
make test
make phpstan
```

---

## 3) Installation en production

## 3.1. Préparer l'environnement

- Déployer le code applicatif (tag/release recommandé).
- Définir les variables d'environnement système (pas dans `.env` versionné):
    - `APP_ENV=prod`
    - `APP_DEBUG=0`
    - `APP_SECRET`
    - `DATABASE_URL`
    - `MAILER_DSN` (si utilisé)
    - `LDAP_*` (si LDAP activé)
    - `MERCURE_*` (si Turbo/Mercure activé)

## 3.2. Installer les dépendances

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```

## 3.3. Préparer Symfony

```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Optionnel (optimisation env Symfony):

```bash
composer dump-env prod
```

## 3.4. Droits et répertoires

Donner les droits d'écriture au user web sur:

- `var/cache/`
- `var/log/`
- `var/sessions/` (si utilisé)

Le document root doit pointer vers `public/`.

## 3.5. Processus asynchrones

Si Messenger est utilisé, lancer un worker supervisé (`systemd`/`supervisor`):

```bash
php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M --env=prod
```

---

## 4) Déploiement type (checklist rapide)

1. Récupérer code + dépendances (`composer install --no-dev`, `npm ci`, `npm run build`).
2. Injecter variables/secrets de prod.
3. Lancer migrations.
4. Vider/réchauffer cache.
5. Redémarrer PHP-FPM/Apache/Nginx + workers Messenger.
6. Vérifier logs applicatifs et endpoint applicatif.

---

## 5) Dépannage rapide

### Les conteneurs ne démarrent pas

```bash
make logs
make ps
```

### Erreur mémoire PHP

- En Docker local, ajuster `docker/php-conf.d/99-custom.ini` (ex: `memory_limit = 1G`), puis redémarrer les conteneurs.

### Problème DB (connexion/migration)

- Vérifier `DATABASE_URL`.
- Vérifier que la base existe et que l'utilisateur a les droits.
- Relancer les migrations.

### Assets manquants

```bash
npm run build
```

---

## 6) Bonnes pratiques

- Ne jamais committer de secrets dans Git.
- Utiliser un fichier `.env.local` en dev et des variables d'environnement système en prod.
- Déployer avec un tag release et migrations versionnées.
- Surveiller `var/log/` et les logs du serveur web.

