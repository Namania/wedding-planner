# Session longue et appareils de confiance — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ramener la session à cinq minutes adossées à un cookie « remember me » d'une semaine à jeton tournant, et permettre de marquer un appareil de confiance qui dispense du TOTP pendant trente jours.

**Architecture:** On ne remplace pas l'authentification par session Sanctum SPA ; on utilise ses deux étages natifs. La session courte est le facteur court terme, le cookie remember le facteur long terme, et un middleware fait tourner son jeton à chaque réutilisation. Les appareils de confiance vivent dans une table dédiée, manipulée par un service écrit contre le contrat `Authenticatable` afin que les futurs comptes invités s'en servent sans dupliquer le contrôleur.

**Tech Stack:** Laravel 13, Sanctum (mode SPA stateful), Vue 3 + TypeScript + PrimeVue 4 + Pinia, PostgreSQL, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-28-session-longue-et-appareils-de-confiance-design.md`

## Global Constraints

- `SESSION_LIFETIME=5` dans `compose.yml`, `compose.prod.yml` et `.env.example` — même valeur en développement et en production.
- `AUTH_REMEMBER_LIFETIME` par défaut `10080` minutes (sept jours).
- `AUTH_TRUSTED_DEVICE_DAYS` par défaut `30`.
- Le jeton d'appareil est haché en **SHA-256**, jamais bcrypt : il doit se retrouver par index.
- Les cookies partent par `->withCookie()` sur la réponse, jamais par `Cookie::queue()`.
- Un appareil de confiance dispense du second facteur, **jamais du mot de passe**.
- Commentaires et libellés en français, comme le reste du dépôt.
- Les tests tournent dans le conteneur : `docker compose exec php php artisan test`.
- Pint doit rester vert : `docker compose exec php ./vendor/bin/pint --test`.

## Review Focus

1. **Cookie `trusted_device` forgé ou illisible** — doit retomber sur le parcours TOTP, jamais produire une 500. Test en tâche 4.
2. **Cookie de confiance valide mais mot de passe faux** — doit être refusé ; le cookie ne remplace jamais le mot de passe. Test en tâche 4.
3. **Reconnexions répétées depuis un appareil déjà de confiance** — ne doivent pas créer de lignes en double, et doivent rafraîchir `last_used_at`. Test en tâche 4.
4. **Révocation de l'appareil courant** — le cookie doit être effacé dans la réponse, sinon le navigateur reste « de confiance » jusqu'à expiration alors que la base ne le connaît plus. Test en tâche 5.
5. **Échec du rafraîchissement CSRF** — l'intercepteur 419 doit abandonner après un seul essai au lieu de boucler. Test en tâche 6.

---

## Structure des fichiers

| Fichier | Responsabilité |
|---|---|
| `config/auth.php` | durées de remember me et d'appareil de confiance |
| `app/Providers/AppServiceProvider.php` | applique la durée du cookie remember à la garde `web` |
| `app/Http/Middleware/RotateRememberToken.php` | fait tourner `remember_token` à chaque reconnexion |
| `database/migrations/2026_09_28_120000_create_two_factor_trusted_devices_table.php` | table des appareils |
| `app/Models/TwoFactorTrustedDevice.php` | modèle |
| `app/Services/TrustedDeviceRegistry.php` | émission, vérification, libellé — contre `Authenticatable` |
| `app/Http/Controllers/AuthController.php` | branchement du parcours de connexion |
| `app/Http/Controllers/TwoFactorDeviceController.php` | liste et révocation |
| `app/Models/User.php` | `requiresTwoFactor()` |
| `resources/src/api/client.ts` | rejeu sur 419 |
| `resources/src/stores/auth.ts` | connexion directe et `trust_device` |
| `resources/src/views/LoginView.vue` | case « se souvenir de cet appareil » |
| `resources/src/components/TwoFactorDevices.vue` | écran de révocation |
| `resources/src/views/SettingsView.vue` | inclut le composant |

---

### Task 1: Session courte et cookie remember d'une semaine

**Files:**
- Modify: `config/auth.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Http/Controllers/AuthController.php` (méthode `completeLogin`)
- Modify: `compose.yml`, `compose.prod.yml`, `.env.example`
- Test: `tests/Feature/TwoFactorAuthTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: `config('auth.remember_lifetime')` (int, minutes) et `config('auth.trusted_device_days')` (int, jours), consommés par les tâches 2, 3 et 4.

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter dans `tests/Feature/TwoFactorAuthTest.php` :

```php
public function test_login_issues_a_remember_cookie_lasting_one_week(): void
{
    $secret = $this->totp()->generateSecret();
    $this->user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ])->save();

    $token = $this->login()->json('challenge_token');

    $response = $this->postJson('/api/two-factor-challenge', [
        'challenge_token' => $token,
        'code' => $this->codeFor($secret),
    ]);

    $response->assertOk();

    $recaller = collect($response->headers->getCookies())
        ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

    $this->assertNotNull($recaller, 'Aucun cookie « remember me » posé à la connexion.');

    // 10080 minutes = sept jours. On tolère une minute de dérive entre le
    // calcul du test et celui du framework.
    $this->assertEqualsWithDelta(
        now()->addMinutes(10080)->timestamp,
        $recaller->getExpiresTime(),
        60,
    );
}
```

Ajouter aussi le helper utilisé ci-dessus, s'il n'existe pas déjà dans la classe :

```php
private function totp(): TwoFactorAuthenticator
{
    return app(TwoFactorAuthenticator::class);
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `docker compose exec php php artisan test --filter=test_login_issues_a_remember_cookie_lasting_one_week`
Expected: FAIL — aucun cookie `remember_web_*` n'est posé, `assertNotNull` échoue.

- [ ] **Step 3: Ajouter les deux durées à la configuration**

Dans `config/auth.php`, avant la clé `'passwords'` :

```php
    /*
    |--------------------------------------------------------------------------
    | Durées de session longue
    |--------------------------------------------------------------------------
    |
    | La session elle-même est volontairement courte (SESSION_LIFETIME) ; c'est
    | le cookie « remember me » qui porte la durée réellement vécue par
    | l'utilisateur. La seconde valeur est la durée pendant laquelle un appareil
    | marqué de confiance dispense du second facteur.
    |
    */

    'remember_lifetime' => (int) env('AUTH_REMEMBER_LIFETIME', 10080),

    'trusted_device_days' => (int) env('AUTH_TRUSTED_DEVICE_DAYS', 30),
```

- [ ] **Step 4: Appliquer la durée à la garde de session**

Dans `app/Providers/AppServiceProvider.php`, ajouter l'import `use Illuminate\Support\Facades\Auth;` puis, au début de `boot()` :

```php
        // Sans cela le cookie « remember me » durerait le défaut de Laravel,
        // soit environ quatre cents jours.
        Auth::guard('web')->setRememberDuration(config('auth.remember_lifetime'));
```

- [ ] **Step 5: Poser le cookie à la connexion**

Dans `app/Http/Controllers/AuthController.php`, méthode `completeLogin`, remplacer :

```php
        Auth::guard('web')->login($user);
```

par :

```php
        // remember: true est ce qui permet à la session de cinq minutes de se
        // rouvrir seule pendant une semaine.
        Auth::guard('web')->login($user, remember: true);
```

- [ ] **Step 6: Lancer le test pour vérifier qu'il passe**

Run: `docker compose exec php php artisan test --filter=test_login_issues_a_remember_cookie_lasting_one_week`
Expected: PASS

- [ ] **Step 7: Raccourcir la session partout**

Dans `.env.example`, `compose.yml` et `compose.prod.yml`, porter `SESSION_LIFETIME` à `5`. Dans `compose.prod.yml` la clé se trouve dans l'ancre `x-back-env`, ligne `SESSION_LIFETIME: "120"` → `SESSION_LIFETIME: "5"`. Ajouter juste au-dessus le commentaire :

```yaml
  # Volontairement court : c'est le cookie « remember me » (AUTH_REMEMBER_LIFETIME)
  # qui porte la durée réellement vécue, et son jeton tourne à chaque
  # réutilisation. Voir app/Http/Middleware/RotateRememberToken.php.
```

- [ ] **Step 8: Vérifier la suite complète**

Run: `docker compose exec php php artisan test`
Expected: PASS — en particulier `GalleryAuthTest`, les invités s'authentifiant par jeton Bearer et non par session.

- [ ] **Step 9: Commit**

```bash
git add config/auth.php app/Providers/AppServiceProvider.php app/Http/Controllers/AuthController.php compose.yml compose.prod.yml .env.example tests/Feature/TwoFactorAuthTest.php
git commit -m "feat: session de cinq minutes adossée à un cookie remember d'une semaine"
```

---

### Task 2: Rotation du jeton remember à chaque reconnexion

**Files:**
- Create: `app/Http/Middleware/RotateRememberToken.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/RememberSessionTest.php`

**Interfaces:**
- Consumes: `config('auth.remember_lifetime')` (tâche 1).
- Produces: l'alias de middleware `remember.rotate`.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/RememberSessionTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RememberSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@exemple.com',
            'password' => 'password',
        ]);
    }

    public function test_reconnecting_through_the_remember_cookie_rotates_the_token(): void
    {
        $this->user->forceFill(['remember_token' => 'jeton-initial'])->save();

        $recaller = $this->user->getKey().'|jeton-initial|'.$this->user->getAuthPassword();

        // Aucune session : seul le cookie remember peut authentifier. C'est
        // exactement la situation d'un retour après cinq minutes d'inactivité.
        //
        // withCookie et non withUnencryptedCookie : Sanctum applique
        // EncryptCookies aux requêtes venant d'un domaine stateful, donc le
        // cookie doit arriver chiffré pour être lu. withCookie s'en charge.
        $response = $this->withCookie('remember_web_'.sha1(\Illuminate\Auth\SessionGuard::class), $recaller)
            ->getJson('/api/user');

        $response->assertOk();

        $this->assertNotSame(
            'jeton-initial',
            $this->user->fresh()->getRememberToken(),
            'Le jeton remember doit tourner à chaque réutilisation du cookie.',
        );
    }

    public function test_the_old_remember_cookie_stops_working_after_rotation(): void
    {
        $this->user->forceFill(['remember_token' => 'jeton-initial'])->save();

        $name = 'remember_web_'.sha1(\Illuminate\Auth\SessionGuard::class);
        $recaller = $this->user->getKey().'|jeton-initial|'.$this->user->getAuthPassword();

        $this->withCookie($name, $recaller)->getJson('/api/user')->assertOk();

        // La session ouverte par la première requête persiste d'un appel de
        // test à l'autre : sans ce vidage, la seconde requête s'authentifierait
        // par la session et ne prouverait rien sur le cookie.
        $this->flushSession();

        // withCookie garde la valeur d'origine : c'est bien l'ancien cookie,
        // celui qu'un attaquant aurait volé, qu'on rejoue ici.
        $this->withCookie($name, $recaller)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `docker compose exec php php artisan test --filter=RememberSessionTest`
Expected: FAIL — le premier échoue sur `assertNotSame` (le jeton n'a pas bougé), le second sur `assertUnauthorized` (l'ancien cookie marche encore).

- [ ] **Step 3: Écrire le middleware**

Créer `app/Http/Middleware/RotateRememberToken.php` :

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fait tourner le jeton « remember me » à chaque fois qu'il sert.
 *
 * Sans cette rotation, la découpe session courte / cookie long n'apporte rien :
 * Laravel ne renouvelle ce jeton qu'à la déconnexion, donc un cookie volé
 * vaudrait une semaine d'accès — exactement comme une session d'une semaine.
 * Avec elle, il ne vaut qu'une seule réutilisation.
 */
class RotateRememberToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // viaRemember() n'est vrai que sur la requête où le cookie a ressuscité
        // la session ; les suivantes passent par la session elle-même. La
        // rotation a donc lieu une fois par reconnexion, pas à chaque requête.
        if (Auth::viaRemember() && ($user = Auth::user()) !== null) {
            $user->forceFill(['remember_token' => Str::random(60)])->save();

            // Réémet le cookie avec le nouveau jeton : sans cela l'ancien
            // resterait valable jusqu'à son expiration.
            Auth::guard('web')->login($user, remember: true);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Déclarer l'alias**

Dans `bootstrap/app.php`, ajouter l'import `use App\Http\Middleware\RotateRememberToken;` puis la ligne dans le tableau `$middleware->alias([...])` :

```php
            'remember.rotate' => RotateRememberToken::class,
```

- [ ] **Step 5: Brancher le middleware sur les routes authentifiées par session**

Dans `routes/api.php`, remplacer :

```php
Route::middleware(['auth:sanctum', 'admin.user'])->group(function () {
```

par :

```php
// remember.rotate se place après auth:sanctum : il a besoin que la garde ait
// déjà résolu l'utilisateur pour savoir si le cookie remember a servi. Il ne
// suppose rien du type de compte et suivra les routes invités le jour où
// elles utiliseront la garde de session.
Route::middleware(['auth:sanctum', 'admin.user', 'remember.rotate'])->group(function () {
```

- [ ] **Step 6: Lancer les tests pour vérifier qu'ils passent**

Run: `docker compose exec php php artisan test --filter=RememberSessionTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/RotateRememberToken.php bootstrap/app.php routes/api.php tests/Feature/RememberSessionTest.php
git commit -m "feat: faire tourner le jeton remember à chaque reconnexion"
```

---

### Task 3: Table, modèle et service des appareils de confiance

**Files:**
- Create: `database/migrations/2026_09_28_120000_create_two_factor_trusted_devices_table.php`
- Create: `app/Models/TwoFactorTrustedDevice.php`
- Create: `app/Services/TrustedDeviceRegistry.php`
- Test: `tests/Feature/TrustedDeviceRegistryTest.php`

**Interfaces:**
- Consumes: `config('auth.trusted_device_days')` (tâche 1).
- Produces, utilisés par les tâches 4 et 5 :
  - `TrustedDeviceRegistry::COOKIE` — constante `string`, vaut `'trusted_device'`
  - `TrustedDeviceRegistry::issueFor(Authenticatable $user, Request $request): \Symfony\Component\HttpFoundation\Cookie`
  - `TrustedDeviceRegistry::findValidFor(Authenticatable $user, ?string $token): ?TwoFactorTrustedDevice`
  - modèle `App\Models\TwoFactorTrustedDevice` avec les attributs `user_id`, `token_hash`, `name`, `user_agent`, `ip_address`, `last_used_at`, `expires_at`

- [ ] **Step 1: Écrire les tests qui échouent**

Créer `tests/Feature/TrustedDeviceRegistryTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrustedDeviceRegistryTest extends TestCase
{
    use RefreshDatabase;

    private TrustedDeviceRegistry $registry;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(TrustedDeviceRegistry::class);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@exemple.com',
            'password' => 'password',
        ]);
    }

    private function request(string $agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/130.0 Safari/537.36'): Request
    {
        return Request::create('/api/login', 'POST', server: [
            'HTTP_USER_AGENT' => $agent,
            'REMOTE_ADDR' => '203.0.113.7',
        ]);
    }

    public function test_it_issues_a_cookie_whose_token_is_stored_hashed(): void
    {
        $cookie = $this->registry->issueFor($this->user, $this->request());

        $device = TwoFactorTrustedDevice::sole();

        $this->assertSame(TrustedDeviceRegistry::COOKIE, $cookie->getName());
        $this->assertNotEmpty($cookie->getValue());

        // Le jeton en clair ne doit jamais toucher la base.
        $this->assertNotSame($cookie->getValue(), $device->token_hash);
        $this->assertSame(hash('sha256', $cookie->getValue()), $device->token_hash);
    }

    public function test_it_records_the_device_context(): void
    {
        $this->registry->issueFor($this->user, $this->request());

        $device = TwoFactorTrustedDevice::sole();

        $this->assertSame($this->user->getKey(), $device->user_id);
        $this->assertSame('Chrome sur macOS', $device->name);
        $this->assertSame('203.0.113.7', $device->ip_address);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $device->expires_at->timestamp, 60);
    }

    public function test_it_finds_a_valid_device_from_its_plain_token(): void
    {
        $cookie = $this->registry->issueFor($this->user, $this->request());

        $this->assertNotNull($this->registry->findValidFor($this->user, $cookie->getValue()));
    }

    public function test_it_rejects_an_expired_device(): void
    {
        $cookie = $this->registry->issueFor($this->user, $this->request());

        TwoFactorTrustedDevice::sole()->forceFill(['expires_at' => now()->subDay()])->save();

        $this->assertNull($this->registry->findValidFor($this->user, $cookie->getValue()));
    }

    public function test_it_rejects_a_device_belonging_to_another_account(): void
    {
        $cookie = $this->registry->issueFor($this->user, $this->request());

        $other = User::create([
            'name' => 'Autre',
            'email' => 'autre@exemple.com',
            'password' => 'password',
        ]);

        $this->assertNull($this->registry->findValidFor($other, $cookie->getValue()));
    }

    public function test_it_rejects_an_unknown_or_empty_token(): void
    {
        $this->assertNull($this->registry->findValidFor($this->user, null));
        $this->assertNull($this->registry->findValidFor($this->user, ''));
        $this->assertNull($this->registry->findValidFor($this->user, 'jeton-inventé'));
    }

    public function test_it_falls_back_to_a_readable_label_for_unknown_agents(): void
    {
        $this->registry->issueFor($this->user, $this->request(''));

        $this->assertSame('Appareil inconnu', TwoFactorTrustedDevice::sole()->name);
    }
}
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `docker compose exec php php artisan test --filter=TrustedDeviceRegistryTest`
Expected: FAIL — `Class "App\Services\TrustedDeviceRegistry" not found`.

- [ ] **Step 3: Écrire la migration**

Créer `database/migrations/2026_09_28_120000_create_two_factor_trusted_devices_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // SHA-256 et non bcrypt : un hash déterministe se retrouve par
            // index, là où bcrypt imposerait de balayer la table et de comparer
            // ligne à ligne à chaque connexion.
            $table->string('token_hash', 64)->unique();

            $table->string('name')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_trusted_devices');
    }
};
```

- [ ] **Step 4: Écrire le modèle**

Créer `app/Models/TwoFactorTrustedDevice.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'token_hash', 'name', 'user_agent', 'ip_address', 'last_used_at', 'expires_at'])]
class TwoFactorTrustedDevice extends Model
{
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Écrire le service**

Créer `app/Services/TrustedDeviceRegistry.php` :

```php
<?php

namespace App\Services;

use App\Models\TwoFactorTrustedDevice;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Appareils dispensés du second facteur.
 *
 * Écrit contre le contrat Authenticatable et non contre User : les comptes
 * invités, quand ils existeront, doivent pouvoir s'en servir sans dupliquer
 * quoi que ce soit.
 */
class TrustedDeviceRegistry
{
    public const COOKIE = 'trusted_device';

    /**
     * Enregistre l'appareil et retourne le cookie à attacher à la réponse.
     * Le jeton en clair n'existe que le temps de cet aller-retour.
     */
    public function issueFor(Authenticatable $user, Request $request): Cookie
    {
        $token = Str::random(60);
        $days = (int) config('auth.trusted_device_days');

        TwoFactorTrustedDevice::create([
            'user_id' => $user->getAuthIdentifier(),
            'token_hash' => $this->hash($token),
            'name' => $this->label($request->userAgent()),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        return cookie(self::COOKIE, $token, $days * 24 * 60);
    }

    public function findValidFor(Authenticatable $user, ?string $token): ?TwoFactorTrustedDevice
    {
        if ($token === null || $token === '') {
            return null;
        }

        return TwoFactorTrustedDevice::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('token_hash', $this->hash($token))
            ->where('expires_at', '>', now())
            ->first();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Libellé court pour l'écran de révocation. On reste sur une heuristique
     * plutôt qu'une dépendance d'analyse de user-agent : il s'agit d'aider à
     * reconnaître un appareil, pas de statistiques.
     */
    private function label(?string $agent): string
    {
        if ($agent === null || $agent === '') {
            return 'Appareil inconnu';
        }

        // L'ordre compte : Edge annonce « Chrome » et « Safari » dans son
        // user-agent, et Chrome annonce « Safari ».
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        $platform = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return match (true) {
            $browser !== null && $platform !== null => $browser.' sur '.$platform,
            $browser !== null => $browser,
            $platform !== null => $platform,
            default => Str::limit($agent, 60),
        };
    }
}
```

- [ ] **Step 6: Lancer les tests pour vérifier qu'ils passent**

Run: `docker compose exec php php artisan migrate && docker compose exec php php artisan test --filter=TrustedDeviceRegistryTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/TwoFactorTrustedDevice.php app/Services/TrustedDeviceRegistry.php tests/Feature/TrustedDeviceRegistryTest.php
git commit -m "feat: table et service des appareils de confiance"
```

---

### Task 4: Brancher les appareils de confiance sur la connexion

**Files:**
- Modify: `app/Http/Controllers/AuthController.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/TrustedDeviceLoginTest.php`

**Interfaces:**
- Consumes: `TrustedDeviceRegistry::COOKIE`, `issueFor()`, `findValidFor()` (tâche 3).
- Produces: `User::requiresTwoFactor(): bool` ; le champ de requête `trust_device` (booléen) accepté par `POST /api/two-factor-setup` et `POST /api/two-factor-challenge` ; la réponse de `POST /api/login` peut désormais contenir directement `user`.

- [ ] **Step 1: Écrire les tests qui échouent**

Créer `tests/Feature/TrustedDeviceLoginTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceRegistry;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use OTPHP\TOTP;
use Tests\TestCase;

class TrustedDeviceLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');

        $this->secret = app(TwoFactorAuthenticator::class)->generateSecret();

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@exemple.com',
            'password' => 'password',
        ]);

        $this->user->forceFill([
            'two_factor_secret' => $this->secret,
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    private function login(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/login', array_merge([
            'email' => 'admin@exemple.com',
            'password' => 'password',
        ], $overrides));
    }

    /** Parcours complet, en demandant que l'appareil soit retenu. */
    private function loginAndTrustDevice(): string
    {
        $token = $this->login()->json('challenge_token');

        $response = $this->postJson('/api/two-factor-challenge', [
            'challenge_token' => $token,
            'code' => TOTP::createFromSecret($this->secret)->now(),
            'trust_device' => true,
        ])->assertOk();

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === TrustedDeviceRegistry::COOKIE);

        $this->assertNotNull($cookie, 'Aucun cookie d\'appareil de confiance posé.');

        // La valeur lue ici est la charge chiffrée produite par EncryptCookies
        // sur la réponse, pas le jeton en clair. C'est pour cela que les tests
        // la rejouent avec withUnencryptedCookie : elle est déjà au format
        // attendu à l'entrée, et le middleware la déchiffrera.
        return $cookie->getValue();
    }

    public function test_a_trusted_device_skips_the_totp_step(): void
    {
        $token = $this->loginAndTrustDevice();

        $this->flushSession();

        $this->withUnencryptedCookie(TrustedDeviceRegistry::COOKIE, $token)
            ->postJson('/api/login', ['email' => 'admin@exemple.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@exemple.com')
            ->assertJsonMissingPath('two_factor');
    }

    public function test_a_trusted_device_never_replaces_the_password(): void
    {
        $token = $this->loginAndTrustDevice();

        $this->flushSession();

        $this->withUnencryptedCookie(TrustedDeviceRegistry::COOKIE, $token)
            ->postJson('/api/login', ['email' => 'admin@exemple.com', 'password' => 'mauvais'])
            ->assertStatus(422);
    }

    public function test_a_forged_cookie_falls_back_to_the_totp_step(): void
    {
        $this->withUnencryptedCookie(TrustedDeviceRegistry::COOKIE, 'cookie-forgé-au-hasard')
            ->postJson('/api/login', ['email' => 'admin@exemple.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('two_factor', 'required');
    }

    public function test_an_expired_device_falls_back_to_the_totp_step(): void
    {
        $token = $this->loginAndTrustDevice();

        TwoFactorTrustedDevice::sole()->forceFill(['expires_at' => now()->subDay()])->save();

        $this->flushSession();

        $this->withUnencryptedCookie(TrustedDeviceRegistry::COOKIE, $token)
            ->postJson('/api/login', ['email' => 'admin@exemple.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('two_factor', 'required');
    }

    public function test_reusing_a_trusted_device_creates_no_duplicate_and_refreshes_last_used(): void
    {
        $token = $this->loginAndTrustDevice();

        TwoFactorTrustedDevice::sole()->forceFill(['last_used_at' => now()->subDays(3)])->save();

        $this->flushSession();

        $this->withUnencryptedCookie(TrustedDeviceRegistry::COOKIE, $token)
            ->postJson('/api/login', ['email' => 'admin@exemple.com', 'password' => 'password'])
            ->assertOk();

        $this->assertSame(1, TwoFactorTrustedDevice::count());
        $this->assertTrue(TwoFactorTrustedDevice::sole()->last_used_at->gt(now()->subMinute()));
    }

    public function test_not_asking_to_trust_the_device_records_nothing(): void
    {
        $token = $this->login()->json('challenge_token');

        $this->postJson('/api/two-factor-challenge', [
            'challenge_token' => $token,
            'code' => TOTP::createFromSecret($this->secret)->now(),
        ])->assertOk();

        $this->assertSame(0, TwoFactorTrustedDevice::count());
    }

    public function test_every_account_currently_requires_the_second_factor(): void
    {
        $fresh = User::create([
            'name' => 'Nouveau',
            'email' => 'nouveau@exemple.com',
            'password' => 'password',
        ]);

        // Ce test épingle le point d'accroche du futur rôle invité : tant que
        // requiresTwoFactor() renvoie true, tout compte neuf est poussé vers
        // l'enrôlement. Le jour où il renverra false pour les invités, c'est ce
        // test qui dira que la bascule a bien eu lieu.
        $this->assertTrue($fresh->requiresTwoFactor());

        $this->postJson('/api/login', [
            'email' => 'nouveau@exemple.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('two_factor', 'setup_required');
    }

    public function test_logging_out_does_not_revoke_the_trusted_device(): void
    {
        $token = $this->loginAndTrustDevice();

        $this->postJson('/api/logout')->assertOk();

        // C'est toute la raison d'être de la fonctionnalité : se déconnecter ne
        // doit pas obliger à ressortir son téléphone à la connexion suivante.
        $this->withUnencryptedCookie(TrustedDeviceRegistry::COOKIE, $token)
            ->postJson('/api/login', ['email' => 'admin@exemple.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@exemple.com');

        $this->assertSame(1, TwoFactorTrustedDevice::count());
    }
}
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `docker compose exec php php artisan test --filter=TrustedDeviceLoginTest`
Expected: FAIL — aucun cookie `trusted_device` n'est posé, `assertNotNull` échoue dans `loginAndTrustDevice`.

- [ ] **Step 3: Ajouter le point d'accroche sur le modèle**

Dans `app/Models/User.php`, après `hasTwoFactorEnabled()` :

```php
    /**
     * Le second facteur est-il imposé à ce compte ?
     *
     * Renvoie une constante pour l'instant : tous les comptes existants sont
     * des comptes d'administration. Quand le rôle invité arrivera, ce sera
     * $this->role !== 'guest', et c'est le seul endroit à reprendre — sans
     * cela, login() pousserait les invités vers l'enrôlement TOTP.
     */
    public function requiresTwoFactor(): bool
    {
        return true;
    }
```

- [ ] **Step 4: Brancher le registre sur le contrôleur**

Dans `app/Http/Controllers/AuthController.php` :

Ajouter l'import `use App\Services\TrustedDeviceRegistry;` et compléter le constructeur :

```php
    public function __construct(
        private readonly TwoFactorAuthenticator $totp,
        private readonly TrustedDeviceRegistry $devices,
    ) {}
```

Dans `login()`, après la ligne `$user = User::where('email', $credentials['email'])->firstOrFail();`, insérer :

```php
        // Un appareil de confiance dispense du second facteur, jamais du mot de
        // passe : on n'arrive ici qu'une fois celui-ci vérifié. Un cookie forgé
        // ou illisible est écarté par EncryptCookies, qui le retire de la
        // requête ; findValidFor reçoit alors null et on repart sur le TOTP.
        $device = $this->devices->findValidFor($user, $request->cookie(TrustedDeviceRegistry::COOKIE));

        if ($device !== null) {
            $device->forceFill(['last_used_at' => now()])->save();

            return $this->completeLogin($request, $user);
        }
```

Puis, juste avant la génération du secret en attente (le bloc `// Pas encore enrôlé`), insérer :

```php
        if (! $user->requiresTwoFactor()) {
            return $this->completeLogin($request, $user);
        }
```

Remplacer la signature et le corps de `completeLogin` :

```php
    private function completeLogin(Request $request, User $user, bool $trustDevice = false)
    {
        Auth::guard('web')->login($user, remember: true);

        $request->session()->regenerate();

        $response = response()->json([
            'user' => $user->fresh(),
        ]);

        // withCookie plutôt que Cookie::queue : la file n'est vidée dans la
        // réponse que par AddQueuedCookiesToResponse, absent du groupe api et
        // seulement appliqué par Sanctum aux requêtes venant d'un domaine
        // déclaré stateful. Dépendre de cet empilement rendrait la pose du
        // cookie silencieusement fragile.
        if ($trustDevice) {
            $response->withCookie($this->devices->issueFor($user, $request));
        }

        return $response;
    }
```

Enfin, dans `twoFactorSetup()` et `twoFactorChallenge()`, remplacer les deux `return $this->completeLogin($request, $user);` finaux par :

```php
        return $this->completeLogin($request, $user, $request->boolean('trust_device'));
```

- [ ] **Step 5: Lancer les tests pour vérifier qu'ils passent**

Run: `docker compose exec php php artisan test --filter=TrustedDeviceLoginTest`
Expected: PASS

- [ ] **Step 6: Vérifier la non-régression**

Run: `docker compose exec php php artisan test`
Expected: PASS — en particulier `TwoFactorAuthTest` et `GalleryAuthTest`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/AuthController.php app/Models/User.php tests/Feature/TrustedDeviceLoginTest.php
git commit -m "feat: un appareil de confiance dispense du second facteur"
```

---

### Task 5: Liste et révocation des appareils

**Files:**
- Create: `app/Http/Controllers/TwoFactorDeviceController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/TrustedDeviceRevocationTest.php`

**Interfaces:**
- Consumes: `TrustedDeviceRegistry` (tâche 3), le parcours de connexion (tâche 4).
- Produces: `GET /api/two-factor/devices`, `DELETE /api/two-factor/devices`, `DELETE /api/two-factor/devices/{device}`. Chaque élément de la liste porte `id`, `name`, `ip_address`, `last_used_at`, `expires_at`, `is_current`.

- [ ] **Step 1: Écrire les tests qui échouent**

Créer `tests/Feature/TrustedDeviceRevocationTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrustedDeviceRevocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@exemple.com',
            'password' => 'password',
        ]);
    }

    private function issueDevice(string $agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/130.0 Safari/537.36'): string
    {
        $request = Request::create('/api/login', 'POST', server: [
            'HTTP_USER_AGENT' => $agent,
            'REMOTE_ADDR' => '203.0.113.7',
        ]);

        // Le service est appelé directement : la valeur retournée est le jeton
        // en clair. Les tests l'envoient donc avec withCookie, qui le chiffre
        // comme le ferait un vrai navigateur ayant reçu la réponse.
        return app(TrustedDeviceRegistry::class)->issueFor($this->user, $request)->getValue();
    }

    public function test_it_lists_the_devices_and_marks_the_current_one(): void
    {
        $current = $this->issueDevice();
        $this->issueDevice('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/605.1');

        $response = $this->actingAs($this->user)
            ->withCookie(TrustedDeviceRegistry::COOKIE, $current)
            ->getJson('/api/two-factor/devices')
            ->assertOk()
            ->assertJsonCount(2);

        $currentRows = collect($response->json())->where('is_current', true);

        $this->assertCount(1, $currentRows);
        $this->assertSame('Chrome sur macOS', $currentRows->first()['name']);
    }

    public function test_revoking_the_current_device_clears_its_cookie(): void
    {
        $token = $this->issueDevice();
        $device = TwoFactorTrustedDevice::sole();

        $response = $this->actingAs($this->user)
            ->withCookie(TrustedDeviceRegistry::COOKIE, $token)
            ->deleteJson('/api/two-factor/devices/'.$device->getKey())
            ->assertOk();

        $this->assertSame(0, TwoFactorTrustedDevice::count());

        $cleared = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === TrustedDeviceRegistry::COOKIE);

        $this->assertNotNull($cleared, 'La réponse doit effacer le cookie de l\'appareil courant.');
        $this->assertTrue(
            $cleared->getValue() === null || $cleared->getValue() === '',
            'Le cookie effacé ne doit plus porter de jeton.',
        );
    }

    public function test_it_cannot_revoke_a_device_of_another_account(): void
    {
        $this->issueDevice();
        $device = TwoFactorTrustedDevice::sole();

        $other = User::create([
            'name' => 'Autre',
            'email' => 'autre@exemple.com',
            'password' => 'password',
        ]);

        $this->actingAs($other)
            ->deleteJson('/api/two-factor/devices/'.$device->getKey())
            ->assertNotFound();

        $this->assertSame(1, TwoFactorTrustedDevice::count());
    }

    public function test_it_revokes_every_device_at_once(): void
    {
        $this->issueDevice();
        $this->issueDevice('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/605.1');

        $this->actingAs($this->user)
            ->deleteJson('/api/two-factor/devices')
            ->assertOk();

        $this->assertSame(0, TwoFactorTrustedDevice::count());
    }

    public function test_the_routes_require_authentication(): void
    {
        $this->getJson('/api/two-factor/devices')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `docker compose exec php php artisan test --filter=TrustedDeviceRevocationTest`
Expected: FAIL — les routes n'existent pas, `assertOk` reçoit 404.

- [ ] **Step 3: Écrire le contrôleur**

Créer `app/Http/Controllers/TwoFactorDeviceController.php` :

```php
<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorTrustedDevice;
use App\Services\TrustedDeviceRegistry;
use Illuminate\Http\Request;

class TwoFactorDeviceController extends Controller
{
    public function __construct(private readonly TrustedDeviceRegistry $devices) {}

    public function index(Request $request)
    {
        $current = $this->devices->findValidFor(
            $request->user(),
            $request->cookie(TrustedDeviceRegistry::COOKIE),
        );

        return TwoFactorTrustedDevice::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('expires_at', '>', now())
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn (TwoFactorTrustedDevice $device) => [
                'id' => $device->getKey(),
                'name' => $device->name,
                'ip_address' => $device->ip_address,
                'last_used_at' => $device->last_used_at,
                'expires_at' => $device->expires_at,
                'is_current' => $current !== null && $current->is($device),
            ]);
    }

    public function destroy(Request $request, TwoFactorTrustedDevice $device)
    {
        // 404 et non 403 : on ne confirme pas l'existence d'un appareil qui
        // appartient à quelqu'un d'autre.
        abort_unless($device->user_id === $request->user()->getAuthIdentifier(), 404);

        $wasCurrent = $this->isCurrent($request, $device);

        $device->delete();

        return $this->respond('Appareil révoqué.', $wasCurrent);
    }

    public function destroyAll(Request $request)
    {
        TwoFactorTrustedDevice::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->delete();

        return $this->respond('Tous les appareils ont été révoqués.', true);
    }

    private function isCurrent(Request $request, TwoFactorTrustedDevice $device): bool
    {
        $current = $this->devices->findValidFor(
            $request->user(),
            $request->cookie(TrustedDeviceRegistry::COOKIE),
        );

        return $current !== null && $current->is($device);
    }

    /**
     * Effacer le cookie est indispensable quand l'appareil révoqué est celui
     * qu'on utilise : sans cela le navigateur continuerait de l'envoyer jusqu'à
     * son expiration, en se croyant de confiance alors que la base ne le
     * connaît plus.
     */
    private function respond(string $message, bool $clearCookie)
    {
        $response = response()->json(['message' => $message]);

        return $clearCookie
            ? $response->withoutCookie(TrustedDeviceRegistry::COOKIE)
            : $response;
    }
}
```

- [ ] **Step 4: Déclarer les routes**

Dans `routes/api.php`, ajouter l'import `use App\Http\Controllers\TwoFactorDeviceController;` puis, dans le groupe `['auth:sanctum', 'admin.user', 'remember.rotate']`, juste après `Route::post('/logout', ...)` :

```php
    // La route de collection est déclarée avant celle à paramètre pour que
    // DELETE /two-factor/devices ne soit jamais interprété comme un appareil
    // nommé « devices ».
    Route::get('two-factor/devices', [TwoFactorDeviceController::class, 'index']);
    Route::delete('two-factor/devices', [TwoFactorDeviceController::class, 'destroyAll']);
    Route::delete('two-factor/devices/{device}', [TwoFactorDeviceController::class, 'destroy']);
```

- [ ] **Step 5: Lancer les tests pour vérifier qu'ils passent**

Run: `docker compose exec php php artisan test --filter=TrustedDeviceRevocationTest`
Expected: PASS

- [ ] **Step 6: Vérifier le style**

Run: `docker compose exec php ./vendor/bin/pint --test`
Expected: PASS. Si Pint signale des écarts, lancer `docker compose exec php ./vendor/bin/pint` puis relancer les tests.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/TwoFactorDeviceController.php routes/api.php tests/Feature/TrustedDeviceRevocationTest.php
git commit -m "feat: lister et révoquer les appareils de confiance"
```

---

### Task 6: Rejeu automatique sur 419 côté SPA

**Files:**
- Modify: `resources/src/api/client.ts`
- Test: `resources/src/api/__tests__/client.spec.ts`

**Interfaces:**
- Consumes: rien côté back.
- Produces: rien de nouveau ; le comportement de `apiClient` change.

> **Note :** le dépôt n'a pas encore de tests front. Si aucun lanceur n'est configuré, installer Vitest en dépendance de développement (`npm i -D vitest`), ajouter `"test": "vitest run"` aux scripts de `resources/package.json`, et créer le fichier de test au chemin indiqué. Les commandes ci-dessous supposent ce réglage.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `resources/src/api/__tests__/client.spec.ts` :

```ts
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import apiClient from '@/api/client'

describe('apiClient', () => {
    let mock: MockAdapter

    beforeEach(() => {
        setActivePinia(createPinia())
        mock = new MockAdapter(apiClient)
    })

    it('rafraîchit le jeton CSRF et rejoue une fois après un 419', async () => {
        let attempts = 0

        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPut('/wedding').reply(() => {
            attempts += 1
            return attempts === 1 ? [419, {}] : [200, { ok: true }]
        })

        const { data } = await apiClient.put('/wedding', {})

        expect(attempts).toBe(2)
        expect(data).toEqual({ ok: true })
    })

    it('abandonne après un seul rejeu si le 419 persiste', async () => {
        let attempts = 0

        mock.onGet('../sanctum/csrf-cookie').reply(204)
        mock.onPut('/wedding').reply(() => {
            attempts += 1
            return [419, {}]
        })

        await expect(apiClient.put('/wedding', {})).rejects.toMatchObject({
            response: { status: 419 },
        })

        // Deux appels au total : l'original et un unique rejeu. Sans le
        // drapeau, l'intercepteur boucle jusqu'à épuisement de la pile.
        expect(attempts).toBe(2)
    })

    it('abandonne si le rafraîchissement CSRF échoue lui-même', async () => {
        mock.onGet('../sanctum/csrf-cookie').reply(500)
        mock.onPut('/wedding').reply(419)

        await expect(apiClient.put('/wedding', {})).rejects.toBeDefined()
    })
})
```

Installer l'adaptateur de test si absent : `npm i -D axios-mock-adapter` depuis `resources/`.

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `docker compose exec node npm run test -- client.spec`
Expected: FAIL — `attempts` vaut 1, la requête n'est jamais rejouée.

- [ ] **Step 3: Écrire l'intercepteur**

Dans `resources/src/api/client.ts`, remplacer l'intercepteur de réponse par :

```ts
import type { InternalAxiosRequestConfig } from 'axios'

/** Marque une requête déjà rejouée après rafraîchissement du jeton CSRF. */
type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        const config = error.config as RetriableConfig | undefined

        // La session ne dure que cinq minutes. Après une pause, le cookie
        // « remember me » rouvre une session neuve — donc un nouveau jeton
        // CSRF, que le SPA n'a pas encore. Sans ce rejeu, la première écriture
        // qui suit toute inactivité échouerait en « Page Expired ». Le drapeau
        // borne la reprise à un seul essai.
        if (error.response?.status === 419 && config && !config._csrfRetried) {
            config._csrfRetried = true

            try {
                await apiClient.get('../sanctum/csrf-cookie')

                return await apiClient(config)
            } catch {
                return Promise.reject(error)
            }
        }

        const authRoutes = ['/login', '/logout', '/two-factor-setup', '/two-factor-challenge']
        const isAuthRequest = authRoutes.includes(config?.url ?? '')

        if (error.response && error.response.status === 401 && !isAuthRequest) {
            const authStore = useAuthStore()
            authStore.clearSession()
        }

        return Promise.reject(error)
    }
);
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `docker compose exec node npm run test -- client.spec`
Expected: PASS

- [ ] **Step 5: Vérifier le lint et les types**

Run: `docker compose exec node sh -c "npx oxlint . && npx eslint . && npm run type-check"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/src/api/client.ts resources/src/api/__tests__/client.spec.ts resources/package.json resources/package-lock.json
git commit -m "feat: rejouer une fois après un 419 dû à la reconnexion silencieuse"
```

---

### Task 7: Case « se souvenir de cet appareil » à la connexion

**Files:**
- Modify: `resources/src/stores/auth.ts`
- Modify: `resources/src/views/LoginView.vue`

**Interfaces:**
- Consumes: `POST /api/login` pouvant renvoyer `user` directement, et `trust_device` sur les deux routes de second facteur (tâche 4).
- Produces: `submitTwoFactor(code: string, trustDevice?: boolean): Promise<void>` dans le store.

- [ ] **Step 1: Traiter la connexion directe dans le store**

Dans `resources/src/stores/auth.ts`, méthode `login()`, après `const { data } = await apiClient.post('/login', credentials);` insérer :

```ts
        // Appareil de confiance : le back a ouvert la session directement, il
        // n'y a pas de second facteur à présenter.
        if (data.user) {
            user.value = data.user;
            resetTwoFactor();
            return;
        }
```

- [ ] **Step 2: Transmettre le choix de l'utilisateur**

Toujours dans `auth.ts`, changer la signature de `submitTwoFactor` :

```ts
    async function submitTwoFactor(code: string, trustDevice = false) {
        const endpoint = twoFactorState.value === 'setup'
            ? '/two-factor-setup'
            : '/two-factor-challenge';

        const { data } = await apiClient.post(endpoint, {
            challenge_token: challengeToken.value,
            code,
            trust_device: trustDevice,
        });

        user.value = data.user;
        resetTwoFactor();
    }
```

- [ ] **Step 3: Ajouter la case dans le formulaire**

Dans `resources/src/views/LoginView.vue`, ajouter l'import `import Checkbox from 'primevue/checkbox'` et la référence `const trustDevice = ref(false)`.

Dans le formulaire de l'étape 2, entre le champ `InputOtp` et le bouton de validation :

```vue
                    <div class="flex items-center gap-2">
                        <Checkbox inputId="trust_device" v-model="trustDevice" binary />
                        <label for="trust_device" class="text-sm text-muted-color">
                            Se souvenir de cet appareil pendant 30 jours
                        </label>
                    </div>
```

- [ ] **Step 4: Passer la valeur et gérer la connexion directe**

Dans le script de `LoginView.vue`, dans `handleTwoFactor`, remplacer l'appel `await authStore.submitTwoFactor(code.value)` par :

```ts
        await authStore.submitTwoFactor(code.value, trustDevice.value)
```

Et dans `handleLogin`, après `await authStore.login({ email: email.value, password: password.value })`, ajouter :

```ts
        // Sur un appareil de confiance, la session est déjà ouverte : il n'y a
        // pas d'étape de second facteur à afficher.
        if (authStore.isAuthenticated) {
            router.push('/')
            return
        }
```

- [ ] **Step 5: Vérifier le lint et les types**

Run: `docker compose exec node sh -c "npx oxlint . && npx eslint . && npm run type-check"`
Expected: PASS

- [ ] **Step 6: Vérifier dans le navigateur**

Se connecter en cochant la case, se déconnecter, se reconnecter : le formulaire TOTP ne doit pas apparaître, et le tableau de bord doit s'afficher directement après le mot de passe.

- [ ] **Step 7: Commit**

```bash
git add resources/src/stores/auth.ts resources/src/views/LoginView.vue
git commit -m "feat: proposer de retenir l'appareil à la vérification en deux étapes"
```

---

### Task 8: Écran de révocation dans les réglages

**Files:**
- Create: `resources/src/components/TwoFactorDevices.vue`
- Modify: `resources/src/views/SettingsView.vue`

**Interfaces:**
- Consumes: `GET /api/two-factor/devices`, `DELETE /api/two-factor/devices`, `DELETE /api/two-factor/devices/{id}` (tâche 5).
- Produces: le composant `TwoFactorDevices`.

- [ ] **Step 1: Écrire le composant**

Créer `resources/src/components/TwoFactorDevices.vue` :

```vue
<template>
    <div
        class="bg-surface-0 dark:bg-surface-900 p-6 rounded-2xl border border-surface-200 dark:border-surface-800 shadow-sm space-y-4">

        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-bold">Appareils de confiance</h2>
                <p class="text-sm text-muted-color">
                    Ces appareils ne demandent pas de code de vérification. Le mot de passe reste exigé.
                </p>
            </div>
            <Button v-if="devices.length > 1" label="Tout révoquer" severity="danger" variant="outlined" size="small"
                class="!rounded-xl shrink-0" :loading="revoking === 'all'" @click="revokeAll" />
        </div>

        <div v-if="isLoading" class="space-y-2">
            <Skeleton height="3.5rem" />
            <Skeleton height="3.5rem" />
        </div>

        <p v-else-if="devices.length === 0" class="text-sm text-muted-color">
            Aucun appareil retenu. Cochez « Se souvenir de cet appareil » à la prochaine connexion.
        </p>

        <ul v-else class="divide-y divide-surface-200 dark:divide-surface-800">
            <li v-for="device in devices" :key="device.id" class="py-3 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="font-medium truncate">
                        {{ device.name }}
                        <Tag v-if="device.is_current" value="Cet appareil" severity="info" class="ml-1 align-middle" />
                    </p>
                    <p class="text-xs text-muted-color">
                        {{ device.ip_address }} — vu {{ formatDate(device.last_used_at) }}, expire
                        {{ formatDate(device.expires_at) }}
                    </p>
                </div>
                <Button icon="pi pi-trash" severity="danger" variant="text" class="!rounded-xl shrink-0"
                    :loading="revoking === device.id" @click="revoke(device.id)" />
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import apiClient from '@/api/client'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { onMounted, ref } from 'vue'

interface TrustedDevice {
    id: number
    name: string
    ip_address: string | null
    last_used_at: string | null
    expires_at: string
    is_current: boolean
}

const devices = ref<TrustedDevice[]>([])
const isLoading = ref(true)
const revoking = ref<number | 'all' | null>(null)

const formatDate = (value: string | null): string => {
    if (!value) return 'jamais'

    return new Date(value).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
}

const fetchDevices = async () => {
    isLoading.value = true
    try {
        const { data } = await apiClient.get<TrustedDevice[]>('/two-factor/devices')
        devices.value = data
    } finally {
        isLoading.value = false
    }
}

const revoke = async (id: number) => {
    revoking.value = id
    try {
        await apiClient.delete(`/two-factor/devices/${id}`)
        await fetchDevices()
    } finally {
        revoking.value = null
    }
}

const revokeAll = async () => {
    revoking.value = 'all'
    try {
        await apiClient.delete('/two-factor/devices')
        await fetchDevices()
    } finally {
        revoking.value = null
    }
}

onMounted(fetchDevices)
</script>
```

- [ ] **Step 2: L'inclure dans les réglages**

Dans `resources/src/views/SettingsView.vue`, ajouter l'import `import TwoFactorDevices from '@/components/TwoFactorDevices.vue'` et, dans le template, juste avant la balise `</div>` fermant `.space-y-6.pb-12` :

```vue
        <TwoFactorDevices />
```

- [ ] **Step 3: Vérifier le lint et les types**

Run: `docker compose exec node sh -c "npx oxlint . && npx eslint . && npm run type-check"`
Expected: PASS

- [ ] **Step 4: Vérifier dans le navigateur**

Ouvrir les réglages : l'appareil courant doit apparaître avec son étiquette, la révocation doit le faire disparaître, et la connexion suivante doit redemander le code TOTP.

- [ ] **Step 5: Vérifier l'ensemble une dernière fois**

Run: `docker compose exec php php artisan test && docker compose exec php ./vendor/bin/pint --test`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/src/components/TwoFactorDevices.vue resources/src/views/SettingsView.vue
git commit -m "feat: écran de révocation des appareils de confiance"
```
