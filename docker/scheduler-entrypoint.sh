#!/bin/sh
set -e

set_ownership() {
    if [ "${USER_ID}" != "82" ] || [ "${GROUP_ID}" != "82" ]; then
        chown ${USER_ID}:${GROUP_ID} "$1"
    else
        chown www-data:www-data "$1"
    fi
}

DB_FILE="/var/www/database/database.sqlite"
[ ! -f "${DB_FILE}" ] && touch "${DB_FILE}" && set_ownership "${DB_FILE}" && chmod 664 "${DB_FILE}"

php artisan migrate --force
echo "Starting Laravel scheduler..."
exec php artisan schedule:work --no-interaction

