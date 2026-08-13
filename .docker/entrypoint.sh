#!/bin/sh
# Refreshes Laravel's config/route/view caches against the env vars actually
# injected into this container (build time has no access to real prod env).
set -e

# Idem côté front : le JS a été buildé avec des jetons de substitution
# (voir resources/.env.production), remplacés ici par les vraies valeurs.
/usr/local/bin/replace-runtime-env.sh

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Même image, deux rôles : APP_ROLE=reverb lance le serveur websocket plutôt
# que FrankenPHP, pour ne pas avoir à maintenir un second Dockerfile juste
# pour changer la commande finale (utile si Reverb est déployé en conteneur
# séparé, ex: deux apps Dokploy).
if [ "$APP_ROLE" = "reverb" ]; then
    exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8080}"
fi

# Par défaut (déploiement en un seul conteneur/une seule app) : Reverb tourne
# en arrière-plan sur un port interne (127.0.0.1, jamais exposé directement) ;
# le Caddyfile proxifie /app/* vers ce port. FrankenPHP reste le process
# principal (PID 1) pour que Docker suive sa santé.
php artisan reverb:start --host=127.0.0.1 --port="${REVERB_SERVER_PORT:-8081}" &

exec "$@"
