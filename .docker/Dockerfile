# Production image: builds the Vue SPA and serves it from the same
# container as the Laravel API, so only one container ships to prod.
# Build from the repo root: docker build -f .docker/Dockerfile .

FROM node:20-alpine AS frontend

WORKDIR /app

COPY resources/package*.json ./
RUN npm ci

COPY resources/ .
RUN npm run build

FROM php:8.5-fpm AS backend

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    zip \
    exif \
    pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .
RUN composer install --no-dev --optimize-autoloader

COPY --from=frontend /public/build ./public/build

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 8080

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
