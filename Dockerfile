# Dockerfile
FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl libicu-dev libzip-dev zip unzip \
    libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
    libxml2-dev && \
    docker-php-ext-install intl pdo pdo_mysql zip gd opcache dom xml

# Set working directory
WORKDIR /var/www/symfony

# Copy application files
COPY . .

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Permissions (optional tweak)
RUN chown -R www-data:www-data /var/www/symfony