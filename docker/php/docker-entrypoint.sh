#!/bin/sh
set -e

if [ -d /var/www/html/storage ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
    chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache
fi

exec docker-php-entrypoint "$@"
