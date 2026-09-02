FROM richarvey/nginx-php-fpm:php8.3

COPY . .

# Configuration image
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1

# Laravel spécifique
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

# Migrations automatiques au démarrage
ENV RUN_MIGRATIONS=1
ENV COMPOSER_OPTS="--no-dev --optimize-autoloader"

CMD ["/start.sh"]