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

# Ensure database directory exists
mkdir -p "${DB_DIR}"
set_ownership "${DB_DIR}"
chmod 775 "${DB_DIR}"

# If database.sqlite exists as a directory (from previous failed file mount), remove it
if [ -d "${DB_FILE}" ]; then
    rm -rf "${DB_FILE}" 2>/dev/null || true
fi

# Create database file if it doesn't exist
if [ ! -f "${DB_FILE}" ]; then
    touch "${DB_FILE}"
    set_ownership "${DB_FILE}"
    chmod 664 "${DB_FILE}"
fi

# Ensure file is writable
chmod 664 "${DB_FILE}" || true
set_ownership "${DB_FILE}" || true

php artisan migrate --force
echo "Starting Laravel scheduler..."
exec php artisan schedule:work --no-interaction

