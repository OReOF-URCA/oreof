#!/usr/bin/env bash

# ==============================================================================
# Script de mise à jour d'ORéOF v2 pour l'environnement de production
# ==============================================================================
# Usage :
#   ./update.sh [options]
#
# Options :
#   --assets, -a         Forcer la recompilation des assets avec Vite (npm run build)
#   --no-assets          Ignorer la compilation des assets Vite
#   --migrations, -m     Exécuter les migrations Doctrine (inactif par défaut)
#   --no-migrations      Ne pas exécuter les migrations Doctrine
#   --no-pull            Ne pas effectuer de 'git pull'
#   --branch=<nom>       Spécifier la branche Git (défaut : v2)
#   --help, -h           Afficher cette aide
# ==============================================================================

set -o pipefail

# Couleurs pour l'affichage
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m' # No Color

log_info()    { echo -e "${BLUE}${BOLD}==>${NC} ${BLUE}$1${NC}"; }
log_success() { echo -e "${GREEN}${BOLD}✔${NC} ${GREEN}$1${NC}"; }
log_warn()    { echo -e "${YELLOW}${BOLD}⚠${NC} ${YELLOW}$1${NC}"; }
log_error()   { echo -e "${RED}${BOLD}✖${NC} ${RED}$1${NC}"; }

# --- 1. Paramètres par défaut & Parsing des arguments ---
BRANCH="${BRANCH:-v2}"
BUILD_ASSETS=""      # auto-détecté si vide
RUN_MIGRATIONS=false # inactif par défaut, activable avec --migrations ou -m
DO_GIT_PULL=true
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

show_help() {
    cat <<EOF
Utilisation : $(basename "$0") [OPTIONS]

Options :
  -a, --assets          Forcer la compilation des assets Vite (npm ci/install + npm run build)
  --no-assets           Ignorer systématiquement la compilation des assets
  -m, --migrations      Exécuter les migrations Doctrine (inactif par défaut)
  --no-migrations       Ne pas exécuter les migrations Doctrine
  --no-pull             Ne pas exécuter git pull
  --branch=<nom>        Branche cible pour git pull (défaut: v2)
  -h, --help            Afficher ce message d'aide

Variables d'environnement optionnelles :
  PHP_BIN               Chemin vers le binaire PHP (défaut: php)
  COMPOSER_BIN          Chemin vers composer (défaut: composer)
  PROJECT_DIR           Chemin vers la racine du projet ORéOF
EOF
    exit 0
}

for arg in "$@"; do
    case "$arg" in
        --assets|-a)
            BUILD_ASSETS=true
            ;;
        --no-assets)
            BUILD_ASSETS=false
            ;;
        --migrations|-m)
            RUN_MIGRATIONS=true
            ;;
        --no-migrations)
            RUN_MIGRATIONS=false
            ;;
        --no-pull)
            DO_GIT_PULL=false
            ;;
        --branch=*)
            BRANCH="${arg#*=}"
            ;;
        --help|-h)
            show_help
            ;;
        *)
            log_warn "Option inconnue ignorée : $arg"
            ;;
    esac
done

# --- 2. Détection du répertoire du projet ---
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

if [ -n "$PROJECT_DIR" ] && [ -f "$PROJECT_DIR/composer.json" ]; then
    TARGET_DIR="$PROJECT_DIR"
elif [ -f "$SCRIPT_DIR/composer.json" ]; then
    # Le script est dans la racine du projet
    TARGET_DIR="$SCRIPT_DIR"
elif [ -d "$SCRIPT_DIR/oreof" ] && [ -f "$SCRIPT_DIR/oreof/composer.json" ]; then
    # Le script est au niveau parent d'un dossier "oreof"
    TARGET_DIR="$SCRIPT_DIR/oreof"
elif [ -d "$SCRIPT_DIR/oreofv2" ] && [ -f "$SCRIPT_DIR/oreofv2/composer.json" ]; then
    # Le script est au niveau parent d'un dossier "oreofv2"
    TARGET_DIR="$SCRIPT_DIR/oreofv2"
elif [ -d "/var/www/html/oreof" ] && [ -f "/var/www/html/oreof/composer.json" ]; then
    TARGET_DIR="/var/www/html/oreof"
elif [ -d "/var/www/html/oreofv2" ] && [ -f "/var/www/html/oreofv2/composer.json" ]; then
    TARGET_DIR="/var/www/html/oreofv2"
else
    log_error "Impossible de trouver le projet ORéOF (composer.json introuvable)."
    log_error "Veuillez définir la variable PROJECT_DIR ou placer ce script dans ou juste au-dessus du dossier de l'application."
    exit 1
fi

log_info "Répertoire du projet ciblé : $TARGET_DIR"
cd "$TARGET_DIR" || { log_error "Impossible d'accéder à $TARGET_DIR"; exit 1; }

# --- 3. Détection des utilisateurs (CLI & Web) et sudo ---
# Utilisateur réel exécutant le script (gère les cas où le script est lancé avec sudo)
if [ -n "$SUDO_USER" ]; then
    CURRENT_USER="$SUDO_USER"
else
    CURRENT_USER="$(id -un)"
fi

# Utilisateur du serveur web (Ubuntu/Debian: www-data, CentOS/RHEL: nginx ou apache)
HTTPD_USER="www-data"
for user_candidate in www-data nginx apache _www; do
    if id "$user_candidate" >/dev/null 2>&1; then
        HTTPD_USER="$user_candidate"
        break
    fi
done

# Déterminer si sudo est nécessaire/disponible pour ajuster les permissions
SUDO_CMD=""
if [ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1; then
    SUDO_CMD="sudo"
fi

# --- 4. Git Pull ---
PREV_COMMIT=$(git rev-parse HEAD 2>/dev/null || echo "")

if [ "$DO_GIT_PULL" = true ]; then
    log_info "Récupération des modifications Git (branche : $BRANCH)..."
    git fetch origin "$BRANCH" || { log_error "Échec du git fetch"; exit 1; }
    git pull origin "$BRANCH" || { log_error "Échec du git pull"; exit 1; }
    log_success "Dépôt Git synchronisé."
else
    log_info "Étape Git ignorée (--no-pull)."
fi

NEW_COMMIT=$(git rev-parse HEAD 2>/dev/null || echo "")

# Détection intelligente des modifications si BUILD_ASSETS n'a pas été explicitement forcé
if [ -z "$BUILD_ASSETS" ]; then
    if [ ! -f "public/build/manifest.json" ] || [ ! -f "public/build/entrypoints.json" ]; then
        log_info "Assets non trouvés dans public/build/ -> compilation automatique requise."
        BUILD_ASSETS=true
    elif [ -n "$PREV_COMMIT" ] && [ "$PREV_COMMIT" != "$NEW_COMMIT" ]; then
        # Vérifier si des fichiers front / config ont changé entre les deux commits
        if git diff --name-only "$PREV_COMMIT" "$NEW_COMMIT" 2>/dev/null | grep -E '^(assets/|templates/|package|vite\.config)' >/dev/null; then
            log_info "Changements front-end détectés dans la mise à jour -> compilation des assets requise."
            BUILD_ASSETS=true
        else
            BUILD_ASSETS=false
        fi
    else
        BUILD_ASSETS=false
    fi
fi

# --- 5. Dépendances PHP (Composer) ---
# IMPORTANT : Composer doit s'exécuter AVANT npm, car les paquets Symfony UX
# dans package.json pointent sur "file:vendor/..." (ex: @symfony/ux-turbo, ux-autocomplete).
log_info "Installation des dépendances PHP (Composer production)..."
$COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction || {
    log_error "Erreur lors de composer install"
    exit 1
}
log_success "Dépendances PHP à jour."

# --- 6. Compilation des assets front-end (Vite) ---
if [ "$BUILD_ASSETS" = true ]; then
    log_info "Compilation des assets avec Vite..."

    if command -v npm >/dev/null 2>&1; then
        if [ -f "package-lock.json" ]; then
            log_info "Installation des dépendances npm..."
            npm ci --prefer-offline 2>/dev/null || npm install --no-audit
        else
            npm install --no-audit
        fi

        log_info "Génération du build Vite (npm run build)..."
        npm run build || { log_error "Échec du build Vite"; exit 1; }
    elif command -v yarn >/dev/null 2>&1; then
        log_warn "npm non trouvé, tentative avec yarn..."
        yarn install --frozen-lockfile 2>/dev/null || yarn install
        yarn build || { log_error "Échec du build Yarn"; exit 1; }
    else
        log_error "Ni npm ni yarn n'a été trouvé. Impossible de compiler les assets Vite !"
        exit 1
    fi

    # Rétro-compatibilité print.css :
    # Vite génère public/build/print-[hash].css référencé par entrypoints.json.
    # Pour ne pas casser le manifeste Reprise tout en assurant la compatibilité
    # avec d'éventuels appels directs à /build/print.css, on COPIE (ne pas 'mv').
    if [ -d "public/build" ]; then
        print_css=$(find public/build -maxdepth 1 -name "print*.css" ! -name "print.css" 2>/dev/null | head -n 1)
        if [ -n "$print_css" ]; then
            cp "$print_css" "public/build/print.css"
            log_info "Fichier print.css copié pour rétro-compatibilité (source: $(basename "$print_css"))."
        fi
    fi

    log_success "Assets Vite générés avec succès."
else
    log_info "Compilation des assets sautée (aucun changement front détecté ou option --no-assets). Utilisez --assets pour forcer."
fi

# --- 7. Migrations de base de données ---
if [ "$RUN_MIGRATIONS" = true ]; then
    log_info "Application des migrations Doctrine..."
    $PHP_BIN bin/console doctrine:migrations:migrate --no-interaction --env=prod || {
        log_error "Erreur lors de l'exécution des migrations de base de données."
        exit 1
    }
    log_success "Base de données à jour."
else
    log_info "Migrations sautées (inactif par défaut, utilisez --migrations ou -m pour exécuter)."
fi

# --- 8. Gestion du cache Symfony (Nettoyage & Warmup) ---
log_info "Gestion du cache Symfony (prod)..."

# On nettoie et réchauffe le cache proprement via la console Symfony.
# Si le cache existant est corrompu ou verrouillé, on supprime le dossier de cache prod avant d'effectuer le warmup.
if ! $PHP_BIN bin/console cache:clear --env=prod --no-debug 2>/dev/null; then
    log_warn "cache:clear a rencontré une erreur (droits ou cache verrouillé). Nettoyage forcé de var/cache/prod..."
    rm -rf var/cache/prod
    $PHP_BIN bin/console cache:warmup --env=prod || {
        log_error "Échec de cache:warmup"
        exit 1
    }
else
    $PHP_BIN bin/console cache:warmup --env=prod 2>/dev/null || true
fi
log_success "Cache de production réchauffé."

# --- 9. Réglage robuste des droits et permissions ---
# Les erreurs serveur 500 après mise à jour sont généralement dues au fait
# que les fichiers créés en CLI par l'utilisateur courant ne sont pas inscriptibles
# par l'utilisateur du serveur web (www-data).
log_info "Configuration des permissions et ACLs pour $HTTPD_USER..."

WRITABLE_DIRS=(
    "var"
    "var/cache"
    "var/log"
    "public/uploads"
    "public/mccc-export"
    "public/temp"
    "public/versioning"
    "public/Docs-Offre"
    "mccc-export"
    "export"
    "versioning_json"
)

# S'assurer que tous les répertoires inscriptibles existent
for dir in "${WRITABLE_DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
    fi
done

# 1. Utilisation des ACLs (méthode officielle et recommandée par Symfony)
if command -v setfacl >/dev/null 2>&1; then
    # Appliquer récursivement (-R) sur les fichiers existants
    setfacl -R -m u:"$HTTPD_USER":rwX -m u:"$CURRENT_USER":rwX var public/uploads 2>/dev/null || \
    $SUDO_CMD setfacl -R -m u:"$HTTPD_USER":rwX -m u:"$CURRENT_USER":rwX var public/uploads 2>/dev/null || true

    # Définir les ACLs par défaut (-dR) pour les futurs fichiers créés par Symfony ou la CLI
    setfacl -dR -m u:"$HTTPD_USER":rwX -m u:"$CURRENT_USER":rwX var public/uploads 2>/dev/null || \
    $SUDO_CMD setfacl -dR -m u:"$HTTPD_USER":rwX -m u:"$CURRENT_USER":rwX var public/uploads 2>/dev/null || true
fi

# 2. Groupe propriétaire et droits de lecture/écriture
$SUDO_CMD chown -R "$CURRENT_USER":"$HTTPD_USER" var 2>/dev/null || \
$SUDO_CMD chown -R :"$HTTPD_USER" var 2>/dev/null || true

# Droits 775/777 sur var/ et les dossiers de cache/log
chmod -R 775 var 2>/dev/null || $SUDO_CMD chmod -R 775 var 2>/dev/null || true
chmod -R 777 var/cache var/log 2>/dev/null || $SUDO_CMD chmod -R 777 var/cache var/log 2>/dev/null || true

for dir in "${WRITABLE_DIRS[@]}"; do
    chmod -R 775 "$dir" 2>/dev/null || $SUDO_CMD chmod -R 775 "$dir" 2>/dev/null || true
done

log_success "Permissions configurées avec succès."

# --- 10. Relance des workers Messenger ---
log_info "Arrêt gracieux des workers Messenger..."
$PHP_BIN bin/console messenger:stop-workers 2>/dev/null || true
log_success "Signal d'arrêt envoyé aux workers."

# --- 11. Rechargement PHP-FPM / OPcache (si disponible) ---
if command -v systemctl >/dev/null 2>&1; then
    for php_service in php8.4-fpm php8.3-fpm php-fpm; do
        if systemctl is-active --quiet "$php_service" 2>/dev/null; then
            log_info "Rechargement de $php_service pour vider l'OPcache..."
            $SUDO_CMD systemctl reload "$php_service" 2>/dev/null || true
            break
        fi
    done
fi

echo ""
echo -e "${GREEN}${BOLD}====================================================${NC}"
echo -e "${GREEN}${BOLD}   🎉 ORéOF v2 a été mis à jour avec succès !       ${NC}"
echo -e "${GREEN}${BOLD}====================================================${NC}"
echo ""
