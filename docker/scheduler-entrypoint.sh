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

# Check if the file mount worked or if we need to create a symlink
if [ ! -f "${DB_FILE}" ] && [ ! -L "${DB_FILE}" ] && [ ! -d "${DB_FILE}" ]; then
    if [ -f "${BACKUP_DB_FILE}" ]; then
        ln -sf "${BACKUP_DB_FILE}" "${DB_FILE}"
    else
        touch "${BACKUP_DB_FILE}"
        set_ownership "${BACKUP_DB_FILE}"
        chmod 664 "${BACKUP_DB_FILE}"
        ln -sf "${BACKUP_DB_FILE}" "${DB_FILE}"
    fi
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

php artisan migrate --force
echo "Starting Laravel scheduler..."
exec php artisan schedule:work --no-interaction

