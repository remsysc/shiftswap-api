FROM docker.io/library/php:8.5-cli-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev \
    libonig-dev \
    libpq-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install \
    curl \
    mbstring \
    opcache \
    pdo_pgsql \
    xml \
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

FROM docker.io/library/node:22-bookworm-slim AS node

FROM php-base AS assets

WORKDIR /app

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/bin/npm /usr/local/bin/npm
COPY --from=node /usr/local/bin/npx /usr/local/bin/npx
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=vendor /app /app

RUN npm ci \
    && npm run build

FROM php-base AS app

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage

USER www-data

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
