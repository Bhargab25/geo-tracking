FROM php:8.3-fpm-alpine

# Install system dependencies & PHP extensions for Postgres and Redis
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    bash \
    $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo pdo_pgsql bcmath zip \
    && apk del $PHPIZE_DEPS

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

# Start Laravel HTTP server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]