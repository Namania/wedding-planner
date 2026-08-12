# Installation

cp .env.example .env
make up

L'API tourne sur http://localhost:8000 et le front sur http://localhost:5173.
Au premier démarrage, le conteneur `php` installe les dépendances Composer,
génère `APP_KEY` et joue les migrations automatiquement.

# Create user

docker compose exec php php artisan tinker

\App\Models\User::create(['name' => 'Admin', 'email' => 'admin@exemple.com', 'password' => bcrypt('password')]);
