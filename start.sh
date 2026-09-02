#!/bin/sh
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan storage:link
php artisan filament:assets
php artisan db:seed --force
php-fpm -D
nginx -g "daemon off;"