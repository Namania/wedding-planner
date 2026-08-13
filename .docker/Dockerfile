# Production image: builds the Vue SPA and serves it, together with the
# Laravel API, from a single FrankenPHP container.
# Build from the repo root: docker build -f .docker/Dockerfile .

FROM node:20-alpine AS frontend

# Vite inline ces variables dans le bundle JS au moment du build : elles
# doivent arriver comme build args (Dokploy: "Build Time Arguments"), pas
# comme simples variables d'environnement du conteneur au runtime.
ARG VITE_APP_NAME
ARG VITE_API_BASE_URL
ARG VITE_ALLOWED_HOST
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_API_BASE_URL=$VITE_API_BASE_URL \
    VITE_ALLOWED_HOST=$VITE_ALLOWED_HOST \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

WORKDIR /app

COPY resources/package*.json ./
RUN npm ci

COPY resources/ .
RUN npm run build

FROM dunglas/frankenphp:1-php8.5-alpine AS backend

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    mbstring \
    zip \
    exif \
    pcntl

COPY .docker/php/opcache-prod.ini $PHP_INI_DIR/conf.d/opcache-prod.ini

# Route /app/* vers Reverb (même conteneur, port interne) en plus du Caddyfile
# par défaut de FrankenPHP — un seul domaine/port exposé pour tout.
COPY .docker/Caddyfile /etc/frankenphp/Caddyfile

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Installed before the rest of the app is copied so this layer is only
# invalidated when composer.json/composer.lock actually change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction

COPY . .
COPY --from=frontend /public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /app /data /config

COPY .docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

ENV SERVER_NAME=":8080"
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
