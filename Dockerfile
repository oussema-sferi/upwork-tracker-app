FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl \
    libonig-dev libxml2-dev libpng-dev libicu-dev libpq-dev libssl-dev

# Install PHP extensions needed for Symfony
RUN docker-php-ext-install pdo pdo_mysql zip intl opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/symfony