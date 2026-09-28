# Image backend : API Laravel servie par FrankenPHP.
#
# Ne contient plus le SPA — le front est une image séparée (.docker/front).
# La même image sert aussi au websocket : APP_ROLE=reverb lance Reverb au lieu
# de FrankenPHP (voir entrypoint.sh), pas besoin d'un second Dockerfile.
#
# Build depuis la racine du dépôt : docker build -f .docker/back/Dockerfile .

FROM dunglas/frankenphp:1-php8.5-alpine

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    mbstring \
    zip \
    exif \
    pcntl \
    gd

COPY .docker/php/opcache-prod.ini $PHP_INI_DIR/conf.d/opcache-prod.ini
COPY .docker/php/uploads.ini $PHP_INI_DIR/conf.d/uploads.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Installé avant le reste du code : cette couche n'est invalidée que si
# composer.json/composer.lock changent réellement.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /app /data /config

COPY .docker/back/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

# Pas de Caddyfile custom : celui livré par l'image convient tel quel pour une
# API (root public/, php_server, skip_install_trust). Traefik termine le TLS,
# on n'écoute donc qu'en HTTP.
ENV SERVER_NAME=":8080"
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
