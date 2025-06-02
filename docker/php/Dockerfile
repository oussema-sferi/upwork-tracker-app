FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libpq-dev libzip-dev zip \
    && docker-php-ext-install intl pdo pdo_mysql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer