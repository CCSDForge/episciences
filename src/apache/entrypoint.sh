#!/bin/bash
# Optionally, include the vhost in the main configuration
echo "Include conf/extra/episciences.conf" >> /usr/local/apache2/conf/httpd.conf
#chown -R www-data:www-data /var/www/data /var/www/cache /var/www/logs
exec "$@"

