FROM docker.io/library/php:8.5-cli-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git \
    libpq-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-install \
    bcmath \
    pcntl \
    pdo_pgsql \
    pgsql \
    zip \
    && rm -rf /var/lib/apt/lists/*

FROM docker.io/library/composer:2 AS composer

FROM php-base AS vendor

WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM php-base AS app

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html

RUN mkdir -p \
    /var/www/.config \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    && chown -R www-data:www-data /var/www bootstrap/cache storage

ENV XDG_CONFIG_HOME=/tmp

USER www-data

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
