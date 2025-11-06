#!/bin/sh
set -e

set_ownership() {
    if [ "${USER_ID}" != "82" ] || [ "${GROUP_ID}" != "82" ]; then
        chown ${USER_ID}:${GROUP_ID} "$1"
    else
        chown www-data:www-data "$1"
    fi
}

# Ensure SQLite database file exists
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
    rm -rf "${DB_FILE}" 2>/dev/null || true
fi

# Always use symlink from project database directory to backups database file
if [ -f "${BACKUP_DB_FILE}" ]; then
    # Remove any existing file or incorrect symlink
    if [ -f "${DB_FILE}" ] && [ ! -L "${DB_FILE}" ]; then
        rm -f "${DB_FILE}" 2>/dev/null || true
    fi
    # Remove existing symlink if it points to wrong location
    if [ -L "${DB_FILE}" ]; then
        CURRENT_TARGET=$(readlink "${DB_FILE}")
        if [ "${CURRENT_TARGET}" != "${BACKUP_DB_FILE}" ]; then
            rm -f "${DB_FILE}" 2>/dev/null || true
        fi
    fi
    # Create symlink
    ln -sf "${BACKUP_DB_FILE}" "${DB_FILE}"
else
    # Create new database file in backups
    if ! touch "${BACKUP_DB_FILE}" 2>/dev/null; then
        echo "ERROR: Failed to create database file at ${BACKUP_DB_FILE}"
        echo "Check that backups directory is writable and has correct permissions"
        exit 1
    fi
    set_ownership "${BACKUP_DB_FILE}"
    chmod 664 "${BACKUP_DB_FILE}"
    # Create symlink
    ln -sf "${BACKUP_DB_FILE}" "${DB_FILE}"
fi

# Verify symlink was created correctly
if [ ! -L "${DB_FILE}" ] && [ ! -f "${DB_FILE}" ]; then
    echo "ERROR: Failed to create database symlink or file at ${DB_FILE}"
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

php artisan migrate --force
echo "Starting Laravel scheduler..."
exec php artisan schedule:work --no-interaction

