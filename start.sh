#!/bin/sh
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan storage:link
php artisan filament:assets
php artisan db:seed --force
php artisan relay:register "Samsung A12" --network=orange
php-fpm -D
nginx -g "daemon off;"