#!/bin/sh

set -e

set_ownership() {
    if [ "${USER_ID}" != "82" ] || [ "${GROUP_ID}" != "82" ]; then
        chown ${USER_ID}:${GROUP_ID} "$1"
    else
        chown www-data:www-data "$1"
    fi
}

# Database is always stored in backups directory for recovery
DB_FILE="/var/www/backups/database/database.sqlite"

# Export DB_DATABASE for Laravel config (only if not already set)
[ -z "${DB_DATABASE}" ] && export DB_DATABASE="${DB_FILE}"

# Ensure database directory exists
mkdir -p /var/www/backups/database
set_ownership /var/www/backups/database
chmod 775 /var/www/backups/database

[ ! -f "${DB_FILE}" ] && touch "${DB_FILE}" && set_ownership "${DB_FILE}" && chmod 664 "${DB_FILE}"

php artisan migrate --force
echo "Starting Laravel scheduler..."
exec php artisan schedule:work --no-interaction

