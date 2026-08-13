#!/bin/sh
# Remplace dans les fichiers du build Vite les jetons posés par
# resources/.env.production par les vraies valeurs, lues depuis les
# variables d'environnement du conteneur au démarrage. Vite inline ces
# valeurs dans le JS au build ; comme il n'y a pas de "runtime" pour du JS
# statique, on fait cette substitution texte une fois, à chaque démarrage.
set -e

BUILD_DIR="${BUILD_DIR:-public/build}"

replace() {
    token="$1"
    value="$2"
    grep -rl "$token" "$BUILD_DIR" 2>/dev/null | while IFS= read -r file; do
        sed -i "s|$token|$value|g" "$file"
    done
}

replace '__RUNTIME_VITE_APP_NAME__' "${VITE_APP_NAME:-Wedding Planner}"
replace '__RUNTIME_VITE_API_BASE_URL__' "${VITE_API_BASE_URL:-}"
replace '__RUNTIME_VITE_ALLOWED_HOST__' "${VITE_ALLOWED_HOST:-}"
replace '__RUNTIME_VITE_REVERB_APP_KEY__' "${VITE_REVERB_APP_KEY:-}"
replace '__RUNTIME_VITE_REVERB_HOST__' "${VITE_REVERB_HOST:-}"
replace '__RUNTIME_VITE_REVERB_PORT__' "${VITE_REVERB_PORT:-443}"
replace '__RUNTIME_VITE_REVERB_SCHEME__' "${VITE_REVERB_SCHEME:-https}"
