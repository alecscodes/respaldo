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
        chown -R ${USER_ID}:${GROUP_ID} "$@"
    else
        chown -R www-data:www-data "$@"
    fi
}

# Generate app key if missing
[ -z "${APP_KEY}" ] && log_info "Generating application key..." && php artisan key:generate --force

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
         /var/www/database

set_ownership /var/www/storage /var/www/bootstrap/cache /var/www/database
find /var/www/storage /var/www/bootstrap/cache /var/www/database -type d -exec chmod 775 {} \;
find /var/www/storage /var/www/bootstrap/cache /var/www/database -type f -exec chmod 664 {} \;

# Fix Git directory permissions if .git exists (for update functionality)
# Best practice: Set permissions at container startup, not at runtime
if [ -d "/var/www/.git" ]; then
    log_info "Setting up Git directory permissions for updates..."

    # CRITICAL: Set ownership of entire working directory
    # This allows Git to modify ANY file during updates (git reset --hard)
    # Without this, Git cannot update bind-mounted files from host
    log_info "Setting ownership of /var/www for Git operations..."
    set_ownership /var/www

    # Set base directory permissions
    find /var/www -type d -not -path "*/vendor/*" -not -path "*/node_modules/*" -exec chmod 755 {} \; 2>/dev/null || true
    find /var/www -type f -not -path "*/vendor/*" -not -path "*/node_modules/*" -exec chmod 644 {} \; 2>/dev/null || true

    # Make shell scripts executable
    find /var/www -type f -name "*.sh" -exec chmod 755 {} \; 2>/dev/null || true

    # Configure Git to trust this directory (must be done before any git commands)
    # We use --global here because Git refuses to run --local on an "unsafe" repo
    git config --global --add safe.directory /var/www 2>/dev/null || true

    log_info "Git directory permissions configured successfully"
fi

# Create storage symlink
[ ! -L /var/www/public/storage ] && log_info "Creating storage symlink..." && php artisan storage:link || true

# Create SQLite database if needed
if [ "${DB_CONNECTION}" = "sqlite" ] || [ -z "${DB_CONNECTION}" ]; then
    DB_FILE="/var/www/database/database.sqlite"
    DB_DIR="/var/www/database"
    BACKUP_DB_FILE="/var/www/backups/database/database.sqlite"
    BACKUP_DB_DIR="/var/www/backups/database"

    # Ensure database directory exists (for migrations/seeders/factories)
    mkdir -p "${DB_DIR}"
    set_ownership "${DB_DIR}"
    chmod 775 "${DB_DIR}"

    # Ensure backups database directory exists
    mkdir -p "${BACKUP_DB_DIR}"
    set_ownership "${BACKUP_DB_DIR}"
    chmod 775 "${BACKUP_DB_DIR}"

    # If database.sqlite exists as a directory (from Docker's failed file mount), remove it
    if [ -d "${DB_FILE}" ]; then
        log_warn "Removing incorrectly created database directory (Docker created directory instead of mounting file)..."
        rm -rf "${DB_FILE}" 2>/dev/null || true
    fi

    # Check if the file mount worked or if we need to create a symlink
    # If Docker successfully mounted the file, it will exist as a regular file
    # If Docker created a directory (because file didn't exist on host), we removed it above
    # If mount point is empty, create symlink to backup file
    if [ ! -f "${DB_FILE}" ] && [ ! -L "${DB_FILE}" ] && [ ! -d "${DB_FILE}" ]; then
        if [ -f "${BACKUP_DB_FILE}" ]; then
            log_info "Database file exists in backups but not at mount point. Creating symlink..."
            ln -sf "${BACKUP_DB_FILE}" "${DB_FILE}"
            log_info "Database symlink created successfully"
        else
            log_info "Creating new SQLite database file in backups directory..."
            touch "${BACKUP_DB_FILE}"
            set_ownership "${BACKUP_DB_FILE}"
            chmod 664 "${BACKUP_DB_FILE}"
            ln -sf "${BACKUP_DB_FILE}" "${DB_FILE}"
            log_info "SQLite database file created successfully"
        fi
    elif [ -f "${DB_FILE}" ] && [ ! -L "${DB_FILE}" ]; then
        # File successfully mounted from backups
        log_info "Database file successfully mounted from backups"
    fi

    # Ensure file is writable
    if [ -f "${DB_FILE}" ] || [ -L "${DB_FILE}" ]; then
        chmod 664 "${DB_FILE}" || true
        set_ownership "${DB_FILE}" || true
    fi
    if [ -f "${BACKUP_DB_FILE}" ]; then
        chmod 664 "${BACKUP_DB_FILE}" || true
        set_ownership "${BACKUP_DB_FILE}" || true
    fi
fi

# Laravel optimizations
log_info "Clearing optimizations..."
php artisan optimize:clear || true

log_info "Running migrations..."
php artisan migrate --force

log_info "Optimizing..."
composer dump-autoload --optimize --no-interaction --quiet
php artisan optimize || true

log_info "Entrypoint setup completed successfully!"
exec php-fpm

