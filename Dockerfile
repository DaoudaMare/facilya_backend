FROM php:8.4-fpm-alpine

# Dépendances système + extensions PHP
RUN apk add --no-cache nginx libpng-dev libzip-dev zip unzip git curl postgresql-dev oniguruma-dev icu-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring zip gd bcmath intl

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN mkdir -p /run/nginx && \
    printf 'server {\n    listen 8080;\n    root /var/www/html/public;\n    index index.php;\n    location / {\n        try_files $uri $uri/ /index.php?$query_string;\n    }\n    location ~ \\.php$ {\n        fastcgi_pass 127.0.0.1:9000;\n        fastcgi_index index.php;\n        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n        include fastcgi_params;\n    }\n}\n' > /etc/nginx/http.d/default.conf

RUN printf '#!/bin/sh\nphp artisan config:cache\nphp artisan route:cache\nphp artisan view:cache\nphp artisan migrate --force\nphp artisan storage:link\nphp artisan filament:assets\nphp-fpm -D\nnginx -g "daemon off;"\n' > /start.sh \
    && chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]