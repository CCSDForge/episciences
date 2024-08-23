#!/bin/bash
chown -R www-data:www-data /var/www/data /var/www/cache /var/www/logs
exec "$@"

