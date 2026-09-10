#!/bin/sh
set -e

mkdir -p \
  storage/framework/sessions \
  storage/framework/views \
  storage/framework/cache \
  storage/logs \
  bootstrap/cache

# Artisan tourne en root au boot : sans ça, storage/logs passe en root
# et php-fpm (www-data) obtient "failed to open stream: Permission denied".
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan storage:link
php artisan filament:assets
php artisan db:seed --force
php artisan relay:register "Samsung A12" --network=orange

# Recoller les droits après les commandes Artisan (fichiers créés en root)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php-fpm -D
nginx -g "daemon off;"
