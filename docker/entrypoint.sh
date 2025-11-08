#!/bin/sh

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo "${RED}[ERROR]${NC} $1"; }

set_ownership() {
    if [ "${USER_ID}" != "82" ] || [ "${GROUP_ID}" != "82" ]; then
        chown -R ${USER_ID}:${GROUP_ID} "$@" 2>/dev/null || true
    else
        chown -R www-data:www-data "$@" 2>/dev/null || true
    fi
}

# Ensure we're in the correct directory
cd /var/www || exit 1

# Fix Git directory permissions if .git exists (for update functionality)
if [ -d "/var/www/.git" ]; then
    log_info "Setting up Git directory permissions for updates..."
    set_ownership /var/www
    find /var/www -type d -exec chmod 755 {} \; 2>/dev/null || true
    find /var/www -type f -exec chmod 664 {} \; 2>/dev/null || true
    git config --global --add safe.directory /var/www 2>/dev/null || true
    log_info "Git directory permissions configured successfully"
fi

# Composer
log_info "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# NPM
log_info "Installing NPM dependencies..."
npm ci

# Build assets
log_info "Building assets..."
npm run build

# Database is always stored in backups directory for recovery
DB_FILE="/var/www/backups/database/database.sqlite"

# Export DB_DATABASE for Laravel config (only if not already set)
[ -z "${DB_DATABASE}" ] && export DB_DATABASE="${DB_FILE}"

# Create SQLite database if needed (stored in backup volume for recovery)
if [ "${DB_CONNECTION}" = "sqlite" ] || [ -z "${DB_CONNECTION}" ]; then
    mkdir -p /var/www/backups/database
    [ ! -f "${DB_FILE}" ] && touch "${DB_FILE}" && set_ownership "${DB_FILE}" && chmod 664 "${DB_FILE}"
fi

# Clear optimizations first to ensure .env is read properly
log_info "Clearing optimizations..."
php artisan optimize:clear || true

# Generate app key if missing
if [ -z "$(grep -E '^\s*APP_KEY\s*=' .env | sed -E 's/^\s*APP_KEY\s*=\s*//;s/"//g')" ]; then
  log_info "Generating application key..."
  php artisan key:generate --force
fi

# Wait for database (non-SQLite)
if [ "${DB_CONNECTION}" != "sqlite" ] && [ -n "${DB_CONNECTION}" ]; then
    log_info "Waiting for database connection..."
    until php artisan migrate:status > /dev/null 2>&1; do
        log_warn "Database unavailable - sleeping..."
        sleep 2
    done
    log_info "Database ready!"
fi

# Setup directories and permissions
log_info "Setting up Laravel directories and permissions..."

mkdir -p /var/www/storage/{app/public,framework/{cache/data,sessions,testing,views},logs} \
         /var/www/bootstrap/cache \
         /var/www/database \
         /var/www/backups/database

set_ownership /var/www/storage /var/www/bootstrap/cache /var/www/database /var/www/backups
find /var/www/storage /var/www/bootstrap/cache /var/www/database /var/www/backups -type d -exec chmod 775 {} \;
find /var/www/storage /var/www/bootstrap/cache /var/www/database /var/www/backups -type f -exec chmod 664 {} \;

# Create storage symlink
[ ! -L /var/www/public/storage ] && log_info "Creating storage symlink..." && php artisan storage:link || true

log_info "Running migrations..."
php artisan migrate --force

log_info "Optimizing..."
composer dump-autoload --optimize --no-interaction --quiet
php artisan optimize || true

log_info "Entrypoint setup completed successfully!"

exec php-fpm

