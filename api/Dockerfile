FROM composer:2 AS vendor
WORKDIR /app
# Filament requires ext-intl — composer install's platform check fails without it even though
# this stage never runs the app itself.
RUN apk add --no-cache icu-dev && docker-php-ext-install intl
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/css resources/css
COPY resources/js resources/js
COPY vite.config.js ./
# Vite's Blade plugin scans for @vite-referenced views to fingerprint correctly. Needs the
# views present at build time, not just the css/js entrypoints.
COPY resources/views resources/views
RUN npm run build

FROM php:8.4-apache AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        unzip \
    && docker-php-ext-install pdo pdo_pgsql opcache intl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Publishes Filament's CSS/JS to public/ at build time — cheaper and more predictable than
# regenerating them on every container start via docker-entrypoint.sh.
RUN php artisan filament:assets

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
