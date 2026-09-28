#!/bin/sh
# Reconstruit les caches Laravel avec les variables réellement injectées dans
# le conteneur : le build n'a aucun accès à l'environnement de déploiement.
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Même image, deux rôles : APP_ROLE=reverb lance le serveur websocket au lieu
# de FrankenPHP. Évite de maintenir un second Dockerfile pour ne changer que
# la commande finale.
#
# Les migrations sont volontairement placées APRÈS ce branchement : le
# conteneur Reverb partage cette image, et deux conteneurs migrant en même
# temps se marchent dessus (erreurs de contrainte d'unicité sur les séquences).
# Seul le back migre.
if [ "$APP_ROLE" = "reverb" ]; then
    exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8080}"
fi

# --force ne force aucune opération destructive : il saute la confirmation
# interactive que Laravel exige quand APP_ENV=production. Sans lui, et faute de
# TTY ici, la commande s'annulerait en silence *avec un code de sortie 0* — le
# conteneur démarrerait avec un schéma non migré.
php artisan migrate --force

exec "$@"
