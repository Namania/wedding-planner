#!/bin/sh
# Refreshes Laravel's config/route/view caches against the env vars actually
# injected into this container (build time has no access to real prod env).
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
