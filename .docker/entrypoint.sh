#!/bin/sh
# Refreshes Laravel's config/route/view caches against the env vars actually
# injected into this container (build time has no access to real prod env).
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Même image, deux rôles : APP_ROLE=reverb lance le serveur websocket plutôt
# que FrankenPHP, pour ne pas avoir à maintenir un second Dockerfile juste
# pour changer la commande finale.
if [ "$APP_ROLE" = "reverb" ]; then
    exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8080}"
fi

exec "$@"
