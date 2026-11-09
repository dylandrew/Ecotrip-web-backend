FROM node:20-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY src ./src
COPY vite.config.js ./vite.config.js
RUN npm run build

FROM composer:2 AS php-dependencies
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.3-apache AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev libonig-dev libpng-dev libjpeg62-turbo-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install curl mbstring fileinfo gd pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY app ./app
COPY scheme ./scheme
COPY public ./public
COPY local-router.php ./local-router.php
COPY composer.json composer.lock ./
COPY --from=php-dependencies /build/vendor ./vendor
COPY --from=frontend /build/public/assets/ecotrip ./public/assets/ecotrip

RUN mkdir -p runtime public/uploads \
    && chown -R www-data:www-data runtime public/uploads \
    && chmod 0775 runtime public/uploads \
    && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

EXPOSE 80
