# Production image: builds the Vue SPA and serves it, together with the
# Laravel API, from a single FrankenPHP container.
# Build from the repo root: docker build -f .docker/Dockerfile .

FROM node:20-alpine AS frontend

# Le build utilise resources/.env.production, qui ne contient que des jetons
# de substitution (__RUNTIME_...__) — les vraies valeurs sont injectées au
# démarrage du conteneur par .docker/replace-runtime-env.sh, à partir de
# variables d'environnement classiques. Pas de "build args" à configurer
# côté plateforme de déploiement (peu fiable selon la plateforme).

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
COPY .docker/replace-runtime-env.sh /usr/local/bin/replace-runtime-env.sh

RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/replace-runtime-env.sh

USER www-data

ENV SERVER_NAME=":8080"
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
