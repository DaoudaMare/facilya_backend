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

RUN mkdir -p /run/nginx

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]