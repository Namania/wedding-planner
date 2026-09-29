# Comptes invités avec rôle — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer l'inscription des invités par prénom et code PIN par un vrai compte — nom, email, mot de passe — porté par la table `users` avec un rôle `guest` qui les tient hors des préparatifs.

**Architecture:** `GalleryGuest` disparaît au profit de lignes `users` porteuses d'une colonne `role`. Les invités s'authentifient par session comme les administrateurs, avec un cookie de reconnexion d'une semaine pour que la session de cinq minutes reste invisible. Les trois endroits qui décidaient « administrateur ou invité » en testant le type de l'objet authentifié passent au rôle — c'est la partie qui compte, le reste est mécanique.

**Tech Stack:** Laravel 13, Sanctum en mode SPA stateful, Vue 3 + TypeScript + PrimeVue 4 + Pinia, PostgreSQL, PHPUnit, Vitest.

**Spec:** `docs/superpowers/specs/2026-09-29-comptes-invites-avec-role-design.md`

## Global Constraints

- Le rôle vaut `admin` ou `guest`, via `User::ROLE_ADMIN` et `User::ROLE_GUEST`.
- La colonne `role` n'a **aucune valeur par défaut** : un compte créé sans rôle explicite doit échouer, pas devenir administrateur.
- Mot de passe invité : **8 caractères minimum**.
- L'unicité porte sur `users.email`, globale aux deux rôles. Plus aucune unicité sur le nom.
- Les cookies partent par `->withCookie()` sur la réponse, jamais par `Cookie::queue()`.
- Inscription et connexion invité ouvrent la session avec `remember: true`.
- Commentaires et libellés en français, comme le reste du dépôt.
- Tests back : `docker compose exec php php artisan test`. Pint : `docker compose exec php ./vendor/bin/pint --test`.
- Tests front : `docker compose exec node npm run test`. Lint et types : `docker compose exec node sh -c "npx oxlint . && npx eslint . && npm run type-check"`.
- Les conteneurs tournent déjà ; ne pas faire de `docker compose up`. Le conteneur PHP a `APP_ENV=production`, donc les migrations demandent `--force`.

## Review Focus

1. **Un invité authentifié appelant une route d'administration** doit recevoir 403, pas 200. C'est la raison d'être du rôle. Test en tâche 1.
2. **Les routes d'administration de la galerie lient `{guest}` à un `User`** : un administrateur ne doit pas pouvoir être banni, supprimé ou voir son mot de passe réinitialisé par ce chemin. Test en tâche 4.
3. **Un email déjà porté par le compte des mariés** doit produire une erreur de validation lisible, sans révéler qu'il s'agit d'un compte d'administration. Test en tâche 3.
4. **Un invité banni pendant sa session** doit être refusé dès la requête suivante, et pas seulement à la connexion. Test en tâche 3.
5. **`galleryEcho.ts` privé de jeton** doit s'autoriser par cookie de session, sinon les photos cessent d'apparaître en direct. Aucun test unitaire ne peut le prouver — l'autorisation se joue entre le navigateur, Reverb et le serveur. C'est la vérification navigateur de la tâche 8 qui en répond, et elle n'est pas optionnelle pour cette raison.

---

## Structure des fichiers

| Fichier | Responsabilité |
|---|---|
| `database/migrations/2026_09_29_100000_add_role_to_users_table.php` | `role`, `banned_at`, `last_seen_at` sur `users` |
| `database/migrations/2026_09_29_100100_move_gallery_photos_to_users.php` | `gallery_guest_id` devient `user_id` |
| `database/migrations/2026_09_29_100200_drop_gallery_guests_table.php` | suppression de la table |
| `app/Models/User.php` | rôle, bannissement, photos, `requiresTwoFactor()` |
| `app/Http/Middleware/EnsureAdminUser.php` | teste le rôle |
| `app/Http/Middleware/EnsureGalleryGuest.php` | teste le rôle et le bannissement |
| `routes/channels.php` | canaux `wedding` et `gallery` par rôle |
| `app/Http/Controllers/GalleryAuthController.php` | inscription et connexion par email |
| `app/Http/Controllers/GalleryAdminController.php` | liste, bannissement, mot de passe temporaire |
| `app/Http/Resources/GuestResource.php` | remplace `GalleryGuestResource` |
| `resources/src/api/galleryClient.ts` | session, rejeu 419 |
| `resources/src/galleryEcho.ts` | autorisation du canal par cookie |
| `resources/src/views/ShareView.vue` | formulaire d'inscription |
| `resources/src/views/GalleryLoginView.vue` | formulaire de connexion |
| `resources/src/views/GalleryView.vue`, `GalleryAdminView.vue` | fin du jeton, mot de passe temporaire |

---

### Task 1: Le rôle sur `users` et les contrôles d'accès

C'est le cœur du chantier. Trois endroits décident aujourd'hui « administrateur ou invité » en testant `instanceof User`. Le jour où les invités sont des `User`, ces trois tests deviennent vrais pour eux.

**Files:**
- Create: `database/migrations/2026_09_29_100000_add_role_to_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Http/Middleware/EnsureAdminUser.php`, `routes/channels.php`
- Test: `tests/Feature/UserRoleTest.php`

**Interfaces:**
- Consumes: rien.
- Produces, utilisés par toutes les tâches suivantes :
  - `User::ROLE_ADMIN` (`'admin'`), `User::ROLE_GUEST` (`'guest'`)
  - `User::isAdmin(): bool`, `User::isGuest(): bool`, `User::isBanned(): bool`
  - `User::photos(): HasMany`
  - état de fabrique `User::factory()->guest()`
  - colonnes `role`, `banned_at`, `last_seen_at` sur `users`

- [ ] **Step 1: Écrire les tests qui échouent**

Créer `tests/Feature/UserRoleTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_a_guest_cannot_reach_the_admin_api(): void
    {
        $guest = User::factory()->guest()->create();

        $this->actingAs($guest)->getJson('/api/guests')->assertForbidden();
        $this->actingAs($guest)->getJson('/api/metrics')->assertForbidden();
        $this->actingAs($guest)->getJson('/api/wedding')->assertForbidden();
    }

    public function test_an_admin_reaches_the_admin_api(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->getJson('/api/wedding')->assertOk();
    }

    public function test_a_guest_is_not_authorised_on_the_wedding_channel(): void
    {
        $guest = User::factory()->guest()->create();
        $admin = User::factory()->create();

        $callback = Broadcast::getChannels()['wedding'] ?? null;
        $this->assertNotNull($callback, 'Le canal wedding doit être déclaré.');

        $this->assertFalse((bool) $callback($guest), 'Un invité ne doit pas écouter les préparatifs.');
        $this->assertTrue((bool) $callback($admin));
    }

    public function test_a_banned_guest_is_not_authorised_on_the_gallery_channel(): void
    {
        $banned = User::factory()->guest()->create(['banned_at' => now()]);
        $guest = User::factory()->guest()->create();
        $admin = User::factory()->create();

        $callback = Broadcast::getChannels()['gallery'] ?? null;
        $this->assertNotNull($callback, 'Le canal gallery doit être déclaré.');

        $this->assertFalse((bool) $callback($banned));
        $this->assertTrue((bool) $callback($guest));
        $this->assertTrue((bool) $callback($admin));
    }

    public function test_a_guest_is_never_pushed_towards_two_factor_enrolment(): void
    {
        $this->assertFalse(User::factory()->guest()->create()->requiresTwoFactor());
        $this->assertTrue(User::factory()->create()->requiresTwoFactor());
    }

    public function test_the_role_has_no_default(): void
    {
        // Un compte créé sans rôle explicite doit échouer plutôt que de
        // devenir administrateur par inadvertance.
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::forceCreate([
            'name' => 'Sans rôle',
            'email' => 'sans-role@exemple.com',
            'password' => 'password',
        ]);
    }
}
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `docker compose exec php php artisan test --filter=UserRoleTest`
Expected: FAIL — l'état de fabrique `guest()` n'existe pas.

- [ ] **Step 3: Écrire la migration**

Créer `database/migrations/2026_09_29_100000_add_role_to_users_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La colonne arrive nullable le temps de renseigner les comptes
        // existants, puis devient obligatoire. Lui donner un défaut
        // transformerait tout oubli en création silencieuse d'administrateur.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->nullable()->index();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
        });

        DB::table('users')->whereNull('role')->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'banned_at', 'last_seen_at']);
        });
    }
};
```

- [ ] **Step 4: Compléter le modèle `User`**

Dans `app/Models/User.php`, ajouter l'import `use Illuminate\Database\Eloquent\Relations\HasMany;`, porter `role` dans l'attribut `#[Fillable([...])]`, et ajouter après `hasTwoFactorEnabled()` :

```php
    public const ROLE_ADMIN = 'admin';

    public const ROLE_GUEST = 'guest';

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isGuest(): bool
    {
        return $this->role === self::ROLE_GUEST;
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GalleryPhoto::class);
    }
```

Ajouter les deux casts dans `casts()` :

```php
            'banned_at' => 'datetime',
            'last_seen_at' => 'datetime',
```

Et remplacer le corps de `requiresTwoFactor()` :

```php
    /**
     * Le second facteur est-il imposé à l'enrôlement de ce compte ?
     *
     * Ne gouverne que l'enrôlement, jamais le challenge : un compte qui a déjà
     * un secret confirmé passera par le TOTP quoi qu'il arrive. Les invités de
     * la galerie en sont dispensés.
     *
     * Pour désactiver le second facteur d'un administrateur, passer par
     * `php artisan user:disable-2fa`.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->role !== self::ROLE_GUEST;
    }
```

- [ ] **Step 5: Ajouter l'état de fabrique**

Dans `database/factories/UserFactory.php`, ajouter `'role' => User::ROLE_ADMIN,` au tableau de `definition()`, l'import `use App\Models\User;` s'il manque, puis :

```php
    /**
     * Un invité de la galerie : pas de second facteur, pas d'accès aux
     * préparatifs.
     */
    public function guest(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_GUEST]);
    }
```

- [ ] **Step 6: Faire porter les contrôles d'accès sur le rôle**

Remplacer le corps de `handle()` dans `app/Http/Middleware/EnsureAdminUser.php` :

```php
        $user = $request->user();

        // Le test portait sur le type de l'objet authentifié. Depuis que les
        // invités sont eux aussi des User, seul le rôle distingue les deux.
        if (! $user instanceof User || ! $user->isAdmin()) {
            abort(403, 'Réservé aux administrateurs.');
        }

        return $next($request);
```

Dans `routes/channels.php`, retirer l'import de `GalleryGuest` et remplacer les deux canaux :

```php
Broadcast::channel('wedding', function ($user) {
    return $user instanceof User && $user->isAdmin();
});

Broadcast::channel('gallery', function ($user) {
    return $user instanceof User
        && ($user->isAdmin() || ! $user->isBanned());
}, ['guards' => ['web']]);
```

- [ ] **Step 7: Lancer les tests pour vérifier qu'ils passent**

Run: `docker compose exec php php artisan migrate --force && docker compose exec php php artisan test --filter=UserRoleTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Models/User.php database/factories/UserFactory.php app/Http/Middleware/EnsureAdminUser.php routes/channels.php tests/Feature/UserRoleTest.php
git commit -m "feat: un rôle sur les comptes, et des contrôles d'accès qui le testent"
```

---

### Task 2: Les photos appartiennent à un compte

**Files:**
- Create: `database/migrations/2026_09_29_100100_move_gallery_photos_to_users.php`
- Modify: `app/Models/GalleryPhoto.php`, `database/factories/GalleryPhotoFactory.php`, `app/Http/Resources/GalleryPhotoResource.php`, `app/Http/Controllers/GalleryPhotoController.php`
- Test: `tests/Feature/GalleryPhotoTest.php`

**Interfaces:**
- Consumes: `User::factory()->guest()`, `User::photos()` (tâche 1).
- Produces: `gallery_photos.user_id`, `GalleryPhoto::guest(): BelongsTo` pointant sur `User`.

- [ ] **Step 1: Écrire la migration**

Créer `database/migrations/2026_09_29_100100_move_gallery_photos_to_users.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trois étapes séparées : Postgres refuse de renommer une colonne
        // encore tenue par une contrainte de clé étrangère.
        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->dropForeign(['gallery_guest_id']);
        });

        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->renameColumn('gallery_guest_id', 'user_id');
        });

        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->renameColumn('user_id', 'gallery_guest_id');
        });

        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->foreign('gallery_guest_id')->references('id')->on('gallery_guests')->cascadeOnDelete();
        });
    }
};
```

- [ ] **Step 2: Repointer le modèle**

Dans `app/Models/GalleryPhoto.php` : remplacer `'gallery_guest_id'` par `'user_id'` dans `$fillable`, retirer l'import de `GalleryGuest`, ajouter celui de `User`, et remplacer la relation :

```php
    /**
     * L'invité qui a envoyé la photo. La relation garde son nom : c'est bien
     * d'un invité qu'il s'agit, même si le modèle est désormais User.
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
```

Dans `database/factories/GalleryPhotoFactory.php`, remplacer `'gallery_guest_id' => GalleryGuest::factory(),` par `'user_id' => User::factory()->guest(),` et ajuster les imports.

Dans `app/Http/Resources/GalleryPhotoResource.php`, remplacer `'guest_id' => $this->gallery_guest_id,` par `'guest_id' => $this->user_id,`. **La clé JSON ne change pas** : le front la consomme telle quelle.

Dans `app/Http/Controllers/GalleryPhotoController.php` ligne 112, remplacer `$photo->gallery_guest_id` par `$photo->user_id`.

- [ ] **Step 3: Adapter les tests existants**

Dans `tests/Feature/GalleryPhotoTest.php`, remplacer chaque `GalleryGuest::factory()` par `User::factory()->guest()` et chaque `gallery_guest_id` par `user_id`, en ajustant les imports. Ne changer aucune assertion.

- [ ] **Step 4: Lancer les tests**

Run: `docker compose exec php php artisan migrate --force && docker compose exec php php artisan test --filter=GalleryPhotoTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models/GalleryPhoto.php database/factories/GalleryPhotoFactory.php app/Http/Resources/GalleryPhotoResource.php app/Http/Controllers/GalleryPhotoController.php tests/Feature/GalleryPhotoTest.php
git commit -m "feat: les photos de la galerie appartiennent à un compte"
```

---

### Task 3: Inscription et connexion par email

**Files:**
- Create: `app/Http/Resources/GuestResource.php`
- Modify: `app/Http/Controllers/GalleryAuthController.php`, `app/Http/Middleware/EnsureGalleryGuest.php`, `app/Models/GallerySettings.php`, `bootstrap/app.php`, `routes/api.php`
- Test: `tests/Feature/GalleryAuthTest.php`

**Interfaces:**
- Consumes: `User::ROLE_GUEST`, `isGuest()`, `isBanned()` (tâche 1).
- Produces : `POST /api/gallery/register` prend `token`, `name`, `email`, `password` ; `POST /api/gallery/login` prend `email`, `password` ; les deux ouvrent une session et renvoient `{ guest }`. `GuestResource` expose `id`, `name`, `email`, `banned`, `photos_count`, `last_seen_at`, `created_at`.

- [ ] **Step 1: Écrire les tests qui échouent**

Dans `tests/Feature/GalleryAuthTest.php`, remplacer les tests d'inscription et de connexion par ceux-ci, et **supprimer** `test_name_is_unique_case_insensitively` et `test_a_guest_token_wins_over_an_admin_session_on_gallery_routes` (le premier n'a plus d'objet, le second disparaît avec `PreferAccessToken` en tâche 5) :

```php
    private function register(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/gallery/register', array_merge([
            'token' => $this->token,
            'name' => 'Camille D.',
            'email' => 'camille@exemple.com',
            'password' => 'motdepasse',
        ], $overrides));
    }

    public function test_registration_creates_a_guest_account_and_opens_a_session(): void
    {
        $response = $this->register()->assertCreated()
            ->assertJsonPath('guest.name', 'Camille D.');

        $user = User::where('email', 'camille@exemple.com')->sole();

        $this->assertSame(User::ROLE_GUEST, $user->role);
        $this->assertTrue(Hash::check('motdepasse', $user->password));

        $recaller = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNotNull($recaller, 'La session invité doit être ouverte avec un cookie de reconnexion.');
    }

    public function test_registration_rejects_an_email_already_taken(): void
    {
        User::factory()->create(['email' => 'maries@exemple.com']);

        $this->register(['email' => 'maries@exemple.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_a_password_of_eight_characters(): void
    {
        $this->register(['password' => 'court'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_login_with_email_and_password(): void
    {
        $this->register();
        $this->flushSession();
        \Illuminate\Support\Facades\Auth::forgetGuards();

        $this->postJson('/api/gallery/login', [
            'email' => 'camille@exemple.com',
            'password' => 'motdepasse',
        ])->assertOk()->assertJsonPath('guest.name', 'Camille D.');
    }

    public function test_an_admin_cannot_log_in_through_the_guest_route(): void
    {
        User::factory()->create(['email' => 'maries@exemple.com', 'password' => 'motdepasse']);

        $this->postJson('/api/gallery/login', [
            'email' => 'maries@exemple.com',
            'password' => 'motdepasse',
        ])->assertStatus(422);
    }

    public function test_a_guest_banned_mid_session_is_refused_on_the_next_request(): void
    {
        $this->register();

        $this->getJson('/api/gallery/me')->assertOk();

        User::where('email', 'camille@exemple.com')->sole()
            ->forceFill(['banned_at' => now()])->save();

        // La garde met l'utilisateur résolu en cache entre deux requêtes d'un
        // même test : sans ce vidage, la seconde requête relirait l'instance
        // d'avant le bannissement et le test passerait pour rien.
        \Illuminate\Support\Facades\Auth::forgetGuards();

        $this->getJson('/api/gallery/me')->assertForbidden();
    }

    public function test_a_guest_cannot_use_the_admin_api(): void
    {
        $this->register();

        $this->getJson('/api/guests')->assertForbidden();
    }

    public function test_an_admin_cannot_use_the_guest_gallery_routes(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/gallery/me')
            ->assertForbidden();
    }
```

Ajouter en tête du fichier les imports `use Illuminate\Support\Facades\Hash;` et retirer celui de `GalleryGuest`.

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `docker compose exec php php artisan test --filter=GalleryAuthTest`
Expected: FAIL — l'inscription attend encore un `pin`.

- [ ] **Step 3: Écrire la ressource**

Créer `app/Http/Resources/GuestResource.php` :

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un compte invité vu par la galerie. Remplace GalleryGuestResource en
 * conservant sa forme, que le front consomme telle quelle ; seul l'email
 * s'y ajoute.
 */
class GuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'banned' => $this->isBanned(),
            'photos_count' => $this->whenCounted('photos'),
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 4: Réécrire l'inscription et la connexion**

Dans `app/Http/Controllers/GalleryAuthController.php`, remplacer les imports de `GalleryGuest` et `GalleryGuestResource` par `App\Models\User` et `App\Http\Resources\GuestResource`, ajouter `use Illuminate\Support\Facades\Auth;`, puis remplacer `register()`, `login()`, `me()` et `logout()` :

```php
    public function register(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            // Le message par défaut dirait « cet email est déjà utilisé », ce
            // qui révélerait que l'adresse des mariés est celle d'un compte.
            'email.unique' => 'Cette adresse ne peut pas être utilisée. Essayez-en une autre.',
        ]);

        $settings = $this->settingsForValidToken($data['token']);

        if (! $settings->registrationsAllowed()) {
            throw ValidationException::withMessages([
                'token' => ['Les inscriptions sont fermées. Contactez les mariés.'],
            ]);
        }

        $guest = User::create([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_GUEST,
        ]);

        $guest->forceFill(['last_seen_at' => now()])->save();

        return $this->openSession($request, $guest, 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guest = User::where('email', $data['email'])->first();

        // Un même message pour toutes les causes : compte inexistant, mot de
        // passe faux, compte d'administration ou compte banni. Les distinguer
        // dirait à un inconnu quelles adresses existent.
        if ($guest === null || ! $guest->isGuest() || $guest->isBanned()
            || ! Hash::check($data['password'], $guest->password)) {
            throw ValidationException::withMessages([
                'email' => ['Adresse ou mot de passe incorrect.'],
            ]);
        }

        $guest->forceFill(['last_seen_at' => now()])->save();

        return $this->openSession($request, $guest);
    }

    public function me(Request $request)
    {
        return new GuestResource($request->user()->loadCount('photos'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    /**
     * remember: true est indispensable ici : la session dure cinq minutes, et
     * un invité sur son téléphone pendant une soirée ne doit pas la vivre.
     */
    private function openSession(Request $request, User $guest, int $status = 200)
    {
        Auth::guard('web')->login($guest, remember: true);

        $request->session()->regenerate();

        return response()->json([
            'guest' => new GuestResource($guest),
        ], $status);
    }
```

- [ ] **Step 5: Faire porter le middleware invité sur le rôle**

Remplacer le corps de `handle()` dans `app/Http/Middleware/EnsureGalleryGuest.php` :

```php
        $guest = $request->user();

        if (! $guest instanceof User || ! $guest->isGuest()) {
            abort(403, 'Réservé aux invités de la galerie.');
        }

        if ($guest->isBanned()) {
            abort(403, 'Cet accès a été désactivé.');
        }

        if ($guest->last_seen_at === null || $guest->last_seen_at->lt(now()->subHour())) {
            $guest->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
```

Ajuster les imports : `App\Models\User` remplace `App\Models\GalleryGuest`.

- [ ] **Step 6: Compter les invités par leur rôle**

Dans `app/Models/GallerySettings.php`, remplacer l'import de `GalleryGuest` par `App\Models\User` et la ligne de comptage :

```php
        return User::where('role', User::ROLE_GUEST)->count() < $this->max_guests;
```

- [ ] **Step 7: Rétablir la protection CSRF sur les routes galerie**

Dans `bootstrap/app.php`, retirer `'api/gallery/*'` de la liste d'exceptions de `validateCsrfTokens`. Elle n'existait que parce que les invités s'authentifiaient par jeton ; avec une session, ils doivent être protégés comme les administrateurs.

Dans `routes/api.php`, ajouter `'remember.rotate'` au groupe de routes galerie, pour que le jeton de reconnexion des invités tourne comme celui des administrateurs :

```php
Route::middleware(['auth:sanctum', 'gallery.guest', 'remember.rotate'])->prefix('gallery')->group(function () {
```

- [ ] **Step 8: Lancer les tests**

Run: `docker compose exec php php artisan test --filter=GalleryAuthTest`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add app/Http/Resources/GuestResource.php app/Http/Controllers/GalleryAuthController.php app/Http/Middleware/EnsureGalleryGuest.php app/Models/GallerySettings.php bootstrap/app.php routes/api.php tests/Feature/GalleryAuthTest.php
git commit -m "feat: les invités s'inscrivent avec un email et un mot de passe"
```

---

### Task 4: L'administration de la galerie sur les comptes

**Files:**
- Modify: `app/Http/Controllers/GalleryAdminController.php`, `app/Http/Resources/GallerySettingsResource.php`, `routes/api.php`
- Test: `tests/Feature/GalleryAdminGuestsTest.php`

**Interfaces:**
- Consumes: `GuestResource`, `User::ROLE_GUEST`, `isGuest()` (tâches 1 et 3).
- Produces : `POST /api/gallery-admin/guests/{guest}/reset-password` renvoyant `{ password, guest }`. Les routes `ban`, `unban`, `reset-password` et `destroyGuest` lient `{guest}` à un `User` et répondent 404 si ce n'est pas un invité.

- [ ] **Step 1: Écrire les tests qui échouent**

Créer `tests/Feature/GalleryAdminGuestsTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GalleryAdminGuestsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');

        $this->admin = User::factory()->create();
    }

    public function test_it_lists_only_the_guests(): void
    {
        User::factory()->guest()->count(2)->create();

        $this->actingAs($this->admin)
            ->getJson('/api/gallery-admin/guests')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_it_resets_a_guest_password(): void
    {
        $guest = User::factory()->guest()->create();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/gallery-admin/guests/'.$guest->getKey().'/reset-password')
            ->assertOk();

        $password = $response->json('password');

        $this->assertNotEmpty($password);
        $this->assertTrue(Hash::check($password, $guest->fresh()->password));
    }

    public function test_it_refuses_to_act_on_an_administrator(): void
    {
        $other = User::factory()->create();

        // Ces routes lient {guest} à un User : sans garde de rôle, un
        // administrateur pourrait être banni ou supprimé par ce chemin.
        $this->actingAs($this->admin)
            ->postJson('/api/gallery-admin/guests/'.$other->getKey().'/reset-password')
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->patchJson('/api/gallery-admin/guests/'.$other->getKey().'/ban')
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->deleteJson('/api/gallery-admin/guests/'.$other->getKey())
            ->assertNotFound();

        $this->assertNotNull($other->fresh());
    }

    public function test_banning_a_guest_hides_their_photos(): void
    {
        $guest = User::factory()->guest()->create();
        \App\Models\GalleryPhoto::factory()->create(['user_id' => $guest->getKey()]);

        $this->actingAs($this->admin)
            ->patchJson('/api/gallery-admin/guests/'.$guest->getKey().'/ban')
            ->assertOk();

        $this->assertNotNull($guest->fresh()->banned_at);
        $this->assertNotNull($guest->photos()->sole()->hidden_at);
    }
}
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `docker compose exec php php artisan test --filter=GalleryAdminGuestsTest`
Expected: FAIL — la route `reset-password` n'existe pas.

- [ ] **Step 3: Réécrire le contrôleur d'administration**

Dans `app/Http/Controllers/GalleryAdminController.php`, remplacer les imports de `GalleryGuest` et `GalleryGuestResource` par `App\Models\User` et `App\Http\Resources\GuestResource`, ajouter `use Illuminate\Support\Str;` si absent, puis remplacer les cinq méthodes :

```php
    public function guests()
    {
        return GuestResource::collection(
            User::where('role', User::ROLE_GUEST)->withCount('photos')->orderBy('name')->get()
        );
    }

    public function ban(User $guest)
    {
        $this->assertGuest($guest);

        $guest->update(['banned_at' => now()]);

        $guest->photos()->whereNull('hidden_at')->get()
            ->each(fn (GalleryPhoto $photo) => $photo->update(['hidden_at' => now()]));

        return new GuestResource($guest->loadCount('photos'));
    }

    public function unban(User $guest)
    {
        $this->assertGuest($guest);

        $guest->update(['banned_at' => null]);

        return new GuestResource($guest->loadCount('photos'));
    }

    public function resetPassword(User $guest)
    {
        $this->assertGuest($guest);

        $password = Str::password(12, symbols: false);

        $guest->update(['password' => $password]);

        return response()->json([
            'password' => $password,
            'guest' => new GuestResource($guest),
        ]);
    }

    public function destroyGuest(User $guest)
    {
        $this->assertGuest($guest);

        $guest->photos()->get()->each(fn (GalleryPhoto $photo) => $photo->delete());
        $guest->delete();

        return response()->json(['message' => 'Compte invité supprimé.']);
    }

    /**
     * Ces routes lient {guest} à un User quelconque. Sans cette garde, un
     * administrateur pourrait être banni ou supprimé par le chemin prévu pour
     * les invités. 404 plutôt que 403 : on ne confirme pas son existence.
     */
    private function assertGuest(User $guest): void
    {
        abort_unless($guest->isGuest(), 404);
    }
```

Dans `export()`, aucune modification n'est nécessaire : la relation `guest` existe toujours.

- [ ] **Step 4: Renommer la route**

Dans `routes/api.php`, remplacer la ligne de réinitialisation :

```php
        Route::post('guests/{guest}/reset-password', [GalleryAdminController::class, 'resetPassword']);
```

- [ ] **Step 5: Compter les invités dans les réglages**

Dans `app/Http/Resources/GallerySettingsResource.php`, remplacer l'import de `GalleryGuest` par `App\Models\User` et le comptage par `User::where('role', User::ROLE_GUEST)->count()`.

- [ ] **Step 6: Lancer les tests**

Run: `docker compose exec php php artisan test --filter=GalleryAdminGuestsTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/GalleryAdminController.php app/Http/Resources/GallerySettingsResource.php routes/api.php tests/Feature/GalleryAdminGuestsTest.php
git commit -m "feat: l'administration de la galerie agit sur les comptes invités"
```

---

### Task 5: Supprimer `GalleryGuest` et `PreferAccessToken`

**Files:**
- Create: `database/migrations/2026_09_29_100200_drop_gallery_guests_table.php`
- Delete: `app/Models/GalleryGuest.php`, `database/factories/GalleryGuestFactory.php`, `app/Http/Resources/GalleryGuestResource.php`, `app/Http/Middleware/PreferAccessToken.php`
- Modify: `bootstrap/app.php`, `routes/api.php`

**Interfaces:**
- Consumes: tout ce qui précède.
- Produces: rien. C'est une tâche de suppression, et sa valeur est de prouver que plus rien ne référence l'ancien modèle.

- [ ] **Step 1: Vérifier qu'aucune référence ne subsiste**

Run: `grep -rn "GalleryGuest\|gallery_guest_id\|prefer.token\|PreferAccessToken" app database tests routes resources/src`
Expected: seules les occurrences dans les fichiers à supprimer et dans les deux migrations d'origine de septembre. Toute autre occurrence doit être traitée avant de continuer.

- [ ] **Step 2: Écrire la migration de suppression**

Créer `database/migrations/2026_09_29_100200_drop_gallery_guests_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gallery_guests');
    }

    public function down(): void
    {
        Schema::create('gallery_guests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('name_normalized', 40)->unique();
            $table->string('pin_hash');
            $table->string('created_ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamps();
        });
    }
};
```

- [ ] **Step 3: Supprimer les fichiers**

```bash
git rm app/Models/GalleryGuest.php database/factories/GalleryGuestFactory.php app/Http/Resources/GalleryGuestResource.php app/Http/Middleware/PreferAccessToken.php
```

Dans `bootstrap/app.php`, retirer l'import de `PreferAccessToken`, son alias `'prefer.token'` et l'appel à `prependToPriorityList` qui le plaçait avant `AuthenticatesRequests`. Dans `routes/api.php`, retirer `'prefer.token'` du groupe galerie s'il y figure encore.

- [ ] **Step 4: Lancer la suite complète**

Run: `docker compose exec php php artisan migrate --force && docker compose exec php php artisan test && docker compose exec php ./vendor/bin/pint --test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor: supprimer GalleryGuest et l'arbitrage de garde devenu inutile"
```

---

### Task 6: Le client galerie passe à la session

**Files:**
- Modify: `resources/src/api/galleryClient.ts`, `resources/src/galleryEcho.ts`
- Test: `resources/src/api/__tests__/galleryClient.spec.ts`

**Interfaces:**
- Consumes: les routes de la tâche 3.
- Produces: `galleryClient` sans jeton ; `getGalleryToken` et `setGalleryToken` n'existent plus.

- [ ] **Step 1: Écrire les tests qui échouent**

Créer `resources/src/api/__tests__/galleryClient.spec.ts` :

```ts
import { beforeEach, describe, expect, it } from 'vitest'
import MockAdapter from 'axios-mock-adapter'
import galleryClient from '@/api/galleryClient'

describe('galleryClient', () => {
    let mock: MockAdapter

    beforeEach(() => {
        mock = new MockAdapter(galleryClient)
    })

    it('envoie les cookies de session plutôt qu\'un en-tête Authorization', () => {
        expect(galleryClient.defaults.withCredentials).toBe(true)
        expect(galleryClient.defaults.withXSRFToken).toBe(true)
    })

    it('rafraîchit le jeton CSRF et rejoue une fois après un 419', async () => {
        let attempts = 0

        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPost('/gallery/photos').reply(() => {
            attempts += 1
            return attempts === 1 ? [419, {}] : [201, { ok: true }]
        })

        const { data } = await galleryClient.post('/gallery/photos', {})

        expect(attempts).toBe(2)
        expect(data).toEqual({ ok: true })
    })

    it('abandonne après un seul rejeu si le 419 persiste', async () => {
        let attempts = 0

        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPost('/gallery/photos').reply(() => {
            attempts += 1
            return [419, {}]
        })

        await expect(galleryClient.post('/gallery/photos', {})).rejects.toMatchObject({
            response: { status: 419 },
        })

        expect(attempts).toBe(2)
    })
})
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `docker compose exec node npm run test -- galleryClient`
Expected: FAIL — `withCredentials` vaut `undefined`.

- [ ] **Step 3: Réécrire le client**

Remplacer entièrement `resources/src/api/galleryClient.ts` :

```ts
import axios from 'axios'
import type { InternalAxiosRequestConfig } from 'axios'

/** Marque une requête déjà rejouée après rafraîchissement du jeton CSRF. */
type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

const galleryClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
    withXSRFToken: true,
})

galleryClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        const config = error.config as RetriableConfig | undefined

        // La session dure cinq minutes. Après une pause, le cookie de
        // reconnexion en rouvre une neuve, donc un nouveau jeton CSRF que le
        // SPA n'a pas encore. Sans ce rejeu, la première photo envoyée après
        // toute inactivité échouerait en « Page Expired ».
        if (error.response?.status === 419 && config && !config._csrfRetried) {
            config._csrfRetried = true

            try {
                await galleryClient.get('../sanctum/csrf-cookie')
            } catch {
                return Promise.reject(error)
            }

            return galleryClient(config)
        }

        const status = error.response?.status

        if (status === 401 || (status === 403 && window.location.pathname.startsWith('/gallery'))) {
            if (window.location.pathname !== '/gallery/login') {
                window.location.href = '/gallery/login'
            }
        }

        return Promise.reject(error)
    }
)

export default galleryClient
```

- [ ] **Step 4: Autoriser le canal temps réel par le cookie**

Dans `resources/src/galleryEcho.ts`, remplacer l'appel qui pose l'en-tête `Authorization` par un envoi de cookie, sur le modèle de `resources/src/echo.ts` :

```ts
                    axios.post(
                        `${appUrl}/broadcasting/auth`,
                        { socket_id: socketId, channel_name: channel.name },
                        { withCredentials: true, withXSRFToken: true }
                    )
```

Retirer l'import de `getGalleryToken`.

- [ ] **Step 5: Lancer les tests, le lint et les types**

Run: `docker compose exec node npm run test && docker compose exec node sh -c "npx oxlint . && npx eslint . && npm run type-check"`
Expected: PASS. Les erreurs de type sur `getGalleryToken` dans les vues seront corrigées à la tâche 7 ; si le `type-check` échoue pour cette seule raison, poursuivre et le revérifier en fin de tâche 7.

- [ ] **Step 6: Commit**

```bash
git add resources/src/api/galleryClient.ts resources/src/galleryEcho.ts resources/src/api/__tests__/galleryClient.spec.ts
git commit -m "feat: la galerie s'authentifie par session côté client"
```

---

### Task 7: Les formulaires d'inscription et de connexion

**Files:**
- Modify: `resources/src/views/ShareView.vue`, `resources/src/views/GalleryLoginView.vue`, `resources/src/views/GalleryView.vue`

**Interfaces:**
- Consumes: `POST /api/gallery/register` (`token`, `name`, `email`, `password`) et `POST /api/gallery/login` (`email`, `password`), qui renvoient `{ guest }` sans jeton.
- Produces: rien.

- [ ] **Step 1: Le formulaire d'inscription**

Dans `resources/src/views/ShareView.vue`, remplacer le champ de code PIN par un champ email et un champ mot de passe :

```vue
                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-xs font-bold uppercase tracking-wider text-muted-color">Votre
                            email</label>
                        <InputText id="email" type="email" v-model.trim="email" placeholder="Ex: camille@exemple.com"
                            class="w-full !rounded-xl" :class="{ 'p-invalid': submitted && !email }" />
                        <small class="text-red-500 font-medium text-xs" v-if="submitted && !email">Ce champ est
                            obligatoire.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-xs font-bold uppercase tracking-wider text-muted-color">Votre
                            mot de passe</label>
                        <Password id="password" v-model="password" placeholder="••••••••" :feedback="false" toggleMask
                            :fluid="true" :inputStyle="{ borderRadius: '0.75rem' }"
                            :class="{ 'p-invalid': submitted && !passwordValid }" />
                        <small class="text-xs text-muted-color">8 caractères minimum. Il vous permettra de vous
                            reconnecter depuis un autre appareil.</small>
                        <small class="text-red-500 font-medium text-xs" v-if="submitted && !passwordValid">8 caractères
                            minimum.</small>
                    </div>
```

Le libellé du champ existant passe de « Votre prénom » à « Votre nom ».

Dans le script : importer `Password from 'primevue/password'`, retirer `getGalleryToken` et `setGalleryToken` de l'import de `galleryClient`, et remplacer l'état et les deux fonctions :

```ts
const email = ref('')
const password = ref('')

const passwordValid = computed(() => password.value.length >= 8)
```

```ts
onMounted(async () => {
    // Plus de jeton à consulter : c'est le serveur qui sait si une session est
    // ouverte, via le cookie envoyé avec la requête.
    try {
        await galleryClient.get('/gallery/me')
        router.replace('/gallery')
        return
    } catch {
        // Pas de session : on reste sur le formulaire.
    }

    try {
        const { data } = await galleryClient.get(`/gallery/invite/${inviteToken.value}`)
        inviteValid.value = true
        registrationsOpen.value = data.registrations_open
        wedding.value = data.wedding
    } catch {
        inviteValid.value = false
    } finally {
        checking.value = false
    }
})
```

Dans `handleRegister`, la garde devient `if (!name.value || !email.value || !passwordValid.value) return`, le corps envoyé devient :

```ts
        await galleryClient.post('/gallery/register', {
            token: inviteToken.value,
            name: name.value,
            email: email.value,
            password: password.value,
        })

        // La session est déjà ouverte côté serveur : il n'y a plus de jeton à
        // stocker, seulement à rediriger.
        router.push('/gallery')
```

Supprimer `pin`, `pinValid` et la destructuration `const { data } =` devenue inutile.

- [ ] **Step 2: Le formulaire de connexion**

Dans `resources/src/views/GalleryLoginView.vue`, remplacer les deux champs par les mêmes que ci-dessus (email et mot de passe, avec `Password` de PrimeVue), retirer `getGalleryToken` / `setGalleryToken` de l'import, et remplacer la garde de montage et l'envoi :

```ts
onMounted(async () => {
    try {
        await galleryClient.get('/gallery/me')
        router.replace('/gallery')
    } catch {
        // Pas de session ouverte : on affiche le formulaire.
    }
})
```

```ts
        await galleryClient.post('/gallery/login', {
            email: email.value,
            password: password.value,
        })

        router.push('/gallery')
```

Le message d'erreur affiché reste celui que renvoie l'API.

- [ ] **Step 3: La déconnexion et la garde de la galerie**

Dans `resources/src/views/GalleryView.vue`, retirer `getGalleryToken` / `setGalleryToken` de l'import et remplacer les deux endroits :

```ts
const handleLogout = async () => {
    try {
        await galleryClient.post('/gallery/logout')
    } finally {
        router.push('/gallery/login')
    }
}
```

```ts
onMounted(async () => {
    // Le serveur seul sait si la session est ouverte ; une erreur ici est
    // déjà traitée par l'intercepteur du client, qui redirige.
    try {
        await galleryClient.get('/gallery/me')
    } catch {
        router.replace('/gallery/login')
        return
    }

    try {
```

Le reste de `onMounted` est inchangé.

- [ ] **Step 4: Vérifier le lint et les types**

Run: `docker compose exec node sh -c "npx oxlint . && npx eslint . && npm run type-check"`
Expected: PASS, plus aucune référence à `getGalleryToken` ou `setGalleryToken` dans le dépôt.

- [ ] **Step 5: Commit**

```bash
git add resources/src/views/ShareView.vue resources/src/views/GalleryLoginView.vue resources/src/views/GalleryView.vue
git commit -m "feat: les invités s'inscrivent et se connectent avec leur email"
```

---

### Task 8: L'écran d'administration de la galerie

**Files:**
- Modify: `resources/src/views/GalleryAdminView.vue`

**Interfaces:**
- Consumes: `POST /api/gallery-admin/guests/{guest}/reset-password` renvoyant `{ password, guest }`, et la liste d'invités qui porte désormais `email`.
- Produces: rien.

- [ ] **Step 1: Adapter l'écran**

Dans `resources/src/views/GalleryAdminView.vue`, l'appel de réinitialisation change de route et de charge utile :

```ts
const resetPassword = async (guest: GuestRow) => {
    const { data } = await apiClient.post(`/gallery-admin/guests/${guest.id}/reset-password`)
    newPassword.value = data.password
    passwordGuestName.value = guest.name
    passwordDialog.value = true
}
```

Renommer en conséquence l'état `pinDialog`, `newPin` et `pinGuestName` en `passwordDialog`, `newPassword` et `passwordGuestName`, ainsi que le type de ligne `GalleryGuestRow` en `GuestRow`, auquel s'ajoute `email: string`.

Le bouton de la ligne passe de `title="Nouveau PIN" @click="resetPin(guest)"` à `title="Nouveau mot de passe" @click="resetPassword(guest)"`.

L'en-tête de la fenêtre passe de « Nouveau code PIN » à « Nouveau mot de passe », et son texte doit dire explicitement que le mot de passe ne sera plus affiché :

```vue
                <p class="text-sm text-muted-color">
                    Transmettez ce mot de passe à {{ passwordGuestName }} — il ne sera plus affiché.
                </p>
```

Enfin, afficher l'email sous le nom de chaque invité dans la liste, dans le style des métadonnées déjà présentes (`text-xs text-muted-color`).

- [ ] **Step 2: Vérifier le lint et les types**

Run: `docker compose exec node sh -c "npx oxlint . && npx eslint . && npm run type-check"`
Expected: PASS

- [ ] **Step 3: Vérifier l'ensemble**

Run: `docker compose exec php php artisan test && docker compose exec php ./vendor/bin/pint --test && docker compose exec node npm run test`
Expected: PASS

- [ ] **Step 4: Vérifier dans le navigateur**

Ouvrir un lien de partage, s'inscrire avec un nom, un email et un mot de passe, envoyer une photo, vérifier qu'elle apparaît en direct, se déconnecter, se reconnecter, puis vérifier qu'une route d'administration est bien refusée.

- [ ] **Step 5: Commit**

```bash
git add resources/src/views/GalleryAdminView.vue
git commit -m "feat: l'administration réinitialise un mot de passe invité"
```
