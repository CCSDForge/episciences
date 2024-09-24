#!/bin/bash
chown -R www-data:www-data /var/www/data /var/www/cache /var/www/logs
# Run Composer as www-data
su - www-data -c "cd /var/www/htdocs/;composer install --no-interaction --prefer-dist --optimize-autoloader"

exec "$@"

