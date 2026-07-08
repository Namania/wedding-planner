# Create user

docker compose exec php php artisan tinker

\App\Models\User::create(['name' => 'Admin', 'email' => 'admin@exemple.com', 'password' => bcrypt('password')]);
