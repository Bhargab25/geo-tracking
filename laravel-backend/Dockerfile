FROM php:8.3-fpm-alpine

# Install system dependencies & PHP extensions for Postgres
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    bash \
    && docker-php-ext-install pdo pdo_pgsql bcmath zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

# Simple server initialization for local development
CMD php artisan serve --host=0.0.0.0 --port=8000