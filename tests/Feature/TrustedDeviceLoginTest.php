<?php

namespace Tests\Feature;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceRegistry;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
            ->withCredentials()
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
            ->withCredentials()
            ->postJson('/api/login', ['email' => 'admin@exemple.com', 'password' => 'mauvais'])
            ->assertStatus(422);
    }

    public function test_a_forged_cookie_falls_back_to_the_totp_step(): void
    {
        $this->withUnencryptedCookie(TrustedDeviceRegistry::COOKIE, 'cookie-forgé-au-hasard')
            ->withCredentials()
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
            ->withCredentials()
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
            ->withCredentials()
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

        // Le middleware auth:sanctum de /api/logout authentifie via la garde
        // 'sanctum', et Authenticate::authenticate() en fait la garde par
        // défaut via Auth::shouldUse() — ce qui réécrit aussi
        // auth.defaults.guard dans le conteneur. En production chaque requête
        // reparties d'un conteneur neuf, ce réglage n'a jamais le temps de
        // fuir ; ici le même conteneur sert tout le test, donc sans le
        // restaurer explicitement, Auth::validate() de la requête suivante
        // s'exécute sur RequestGuard (sanctum) au lieu de SessionGuard (web).
        Auth::forgetGuards();
        Auth::shouldUse('web');

        // C'est toute la raison d'être de la fonctionnalité : se déconnecter ne
        // doit pas obliger à ressortir son téléphone à la connexion suivante.
        $this->withUnencryptedCookie(TrustedDeviceRegistry::COOKIE, $token)
            ->withCredentials()
            ->postJson('/api/login', ['email' => 'admin@exemple.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@exemple.com');

        $this->assertSame(1, TwoFactorTrustedDevice::count());
    }
}
