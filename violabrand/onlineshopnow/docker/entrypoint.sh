#!/bin/sh
set -e
PORT="${PORT:-10000}"
# Render tells the app which port to listen on.
sed -ri "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
mkdir -p /var/www/html/uploads/products /var/www/html/uploads/banners /var/www/html/uploads/site
chown -R www-data:www-data /var/www/html/uploads
exec docker-php-entrypoint "$@"
