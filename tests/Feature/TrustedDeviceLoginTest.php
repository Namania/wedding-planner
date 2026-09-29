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

        $this->user = User::factory()->create([
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

        // Le 422 seul ne prouve pas qu'aucune session n'a été ouverte au
        // passage : on vérifie explicitement qu'aucun utilisateur n'est
        // authentifié après ce refus.
        //
        // forgetGuards() : la garde 'web' résolue par loginAndTrustDevice()
        // plus haut garde son utilisateur en cache dans le conteneur, partagé
        // par toutes les requêtes de ce test. flushSession() vide les données
        // de session mais pas ce cache d'instance ; sans le vider aussi, la
        // requête suivante verrait cet utilisateur encore authentifié quel
        // que soit l'état réel de la session.
        Auth::forgetGuards();

        $this->withCredentials()
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_a_forged_cookie_falls_back_to_the_totp_step(): void
    {
        // Un appareil valide doit exister en base : sinon findValidFor()
        // n'aurait rien trouvé quelle que soit la valeur du cookie, et le test
        // passerait à l'identique même si EncryptCookies était absent du
        // pipeline. En rejouant un cookie corrompu à la place du vrai, on
        // prouve que c'est bien le déchiffrement qui échoue, pas l'absence
        // d'appareil enregistré.
        $this->loginAndTrustDevice();

        $this->flushSession();

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

    /**
     * `user:disable-2fa` est le filet de sécurité du projet : téléphone perdu,
     * compte peut-être compromis. Effacer le seul secret ne suffirait pas —
     * login() teste l'appareil de confiance AVANT hasTwoFactorEnabled(), donc
     * un navigateur portant encore un cookie valide rouvrirait une session sur
     * un compte désormais dépourvu de second facteur.
     */
    public function test_disabling_two_factor_revokes_the_trusted_devices_and_the_remember_token(): void
    {
        $token = $this->loginAndTrustDevice();

        $rememberBefore = $this->user->fresh()->getRememberToken();
        $this->assertNotNull($rememberBefore);

        $this->artisan('user:disable-2fa', ['email' => 'admin@exemple.com'])
            ->assertSuccessful();

        $this->assertSame(0, TwoFactorTrustedDevice::count());
        $this->assertNotSame($rememberBefore, $this->user->fresh()->getRememberToken());

        $this->flushSession();
        Auth::forgetGuards();

        $this->withUnencryptedCookie(TrustedDeviceRegistry::COOKIE, $token)
            ->withCredentials()
            ->postJson('/api/login', ['email' => 'admin@exemple.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('two_factor', 'setup_required');
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

    /** Le chemin que tout nouvel admin emprunte : premier enrôlement TOTP. */
    public function test_trusting_the_device_during_first_enrolment_records_it(): void
    {
        $fresh = User::factory()->create([
            'name' => 'Nouveau',
            'email' => 'nouveau@exemple.com',
            'password' => 'password',
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'nouveau@exemple.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('two_factor', 'setup_required');

        $response = $this->postJson('/api/two-factor-setup', [
            'challenge_token' => $login->json('challenge_token'),
            'code' => TOTP::createFromSecret($login->json('secret'))->now(),
            'trust_device' => true,
        ])->assertOk();

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === TrustedDeviceRegistry::COOKIE);

        $this->assertNotNull($cookie, 'Aucun cookie d\'appareil de confiance posé lors du premier enrôlement.');
        $this->assertSame(1, TwoFactorTrustedDevice::query()->where('user_id', $fresh->id)->count());
    }

    public function test_an_admin_account_requires_the_second_factor(): void
    {
        $fresh = User::factory()->create([
            'name' => 'Nouveau',
            'email' => 'nouveau@exemple.com',
            'password' => 'password',
        ]);

        // Le rôle invité existe désormais (voir UserRoleTest) et en est
        // dispensé ; seuls les comptes d'administration, comme celui-ci,
        // sont poussés vers l'enrôlement à leur première connexion.
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
            ->withCredentials()
            ->postJson('/api/login', ['email' => 'admin@exemple.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@exemple.com');

        $this->assertSame(1, TwoFactorTrustedDevice::count());
    }
}
