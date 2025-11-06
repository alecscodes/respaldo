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

    # Always use symlink from project database directory to backups database file
    # This ensures migrations/seeders/factories remain accessible while database is stored in backups
    if [ -f "${BACKUP_DB_FILE}" ]; then
        log_info "Database file found in backups. Creating symlink..."
        # Remove any existing file or incorrect symlink
        if [ -f "${DB_FILE}" ] && [ ! -L "${DB_FILE}" ]; then
            log_warn "Removing existing database file (will use symlink to backups)..."
            rm -f "${DB_FILE}" 2>/dev/null || true
        fi
        # Remove existing symlink if it points to wrong location
        if [ -L "${DB_FILE}" ]; then
            CURRENT_TARGET=$(readlink "${DB_FILE}")
            if [ "${CURRENT_TARGET}" != "${BACKUP_DB_FILE}" ]; then
                log_warn "Removing incorrect symlink (points to ${CURRENT_TARGET})..."
                rm -f "${DB_FILE}" 2>/dev/null || true
            fi
        fi
        # Create symlink
        ln -sf "${BACKUP_DB_FILE}" "${DB_FILE}"
        log_info "Database symlink created successfully"
    else
        log_info "Creating new SQLite database file in backups directory..."
        if ! touch "${BACKUP_DB_FILE}" 2>/dev/null; then
            log_error "Failed to create database file at ${BACKUP_DB_FILE}"
            log_error "Check that backups directory is writable and has correct permissions"
            exit 1
        fi
        set_ownership "${BACKUP_DB_FILE}"
        chmod 664 "${BACKUP_DB_FILE}"
        # Create symlink
        ln -sf "${BACKUP_DB_FILE}" "${DB_FILE}"
        log_info "SQLite database file created successfully in backups"
    fi

    # Verify symlink was created correctly
    if [ ! -L "${DB_FILE}" ] && [ ! -f "${DB_FILE}" ]; then
        log_error "Failed to create database symlink or file at ${DB_FILE}"
        exit 1
    fi

    # Ensure file is writable
    if [ -L "${DB_FILE}" ]; then
        # Follow symlink to set permissions on actual file
        REAL_FILE=$(readlink -f "${DB_FILE}" 2>/dev/null || readlink "${DB_FILE}")
        if [ -f "${REAL_FILE}" ]; then
            chmod 664 "${REAL_FILE}" || true
            set_ownership "${REAL_FILE}" || true
        fi
        # Also set permissions on symlink itself
        chmod 664 "${DB_FILE}" || true
        set_ownership "${DB_FILE}" || true
    elif [ -f "${DB_FILE}" ]; then
        chmod 664 "${DB_FILE}" || true
        set_ownership "${DB_FILE}" || true
    fi

    # Ensure backup file is writable
    if [ -f "${BACKUP_DB_FILE}" ]; then
        chmod 664 "${BACKUP_DB_FILE}" || true
        set_ownership "${BACKUP_DB_FILE}" || true
    fi

    # Verify database file is accessible
    if [ -L "${DB_FILE}" ] || [ -f "${DB_FILE}" ]; then
        if sqlite3 "${DB_FILE}" "SELECT 1;" > /dev/null 2>&1; then
            log_info "Database file is accessible and valid"
        else
            log_warn "Database file exists but may not be accessible. This is normal for a new database."
        fi
    fi
fi

# Build frontend assets
log_info "Building frontend assets..."
cd /var/www && npm run build

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

