#!/bin/sh
set -e

cd /var/www

if [ -f .env ]; then
  set -a
  . ./.env
  set +a
fi

if [ ! -f vendor/autoload.php ]; then
  echo "Installing composer dependencies..."
  composer install --optimize-autoloader
fi

if [ -z "$APP_KEY" ]; then
  echo "Generating application key..."
  php artisan key:generate --ansi
fi

DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-5432}

echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."
until php -r "exit(@fsockopen('${DB_HOST}', ${DB_PORT}) ? 0 : 1);" 2>/dev/null; do
  sleep 1
done

php artisan migrate --force

php artisan db:seed --class=DefaultDataSeeder --force

exec "$@"
