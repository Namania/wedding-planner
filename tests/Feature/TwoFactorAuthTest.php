<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use OTPHP\TOTP;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // La connexion admin repose sur une session Sanctum « stateful » : sans
        // origine déclarée, aucune session n'est ouverte et le login échoue.
        // On fixe la config plutôt que de dépendre du .env de la machine.
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@exemple.com',
            'password' => 'password',
        ]);
    }

    private function codeFor(string $secret): string
    {
        return TOTP::createFromSecret($secret)->now();
    }

    private function login(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/login', array_merge([
            'email' => 'admin@exemple.com',
            'password' => 'password',
        ], $overrides));
    }

    // --- Étape 1 : mot de passe ---------------------------------------------

    public function test_login_rejects_wrong_password(): void
    {
        $this->login(['password' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_never_opens_a_session_without_the_second_factor(): void
    {
        $this->login()->assertOk();

        $this->assertGuest();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_asks_for_enrolment_when_two_factor_is_not_configured(): void
    {
        $response = $this->login()
            ->assertOk()
            ->assertJsonPath('two_factor', 'setup_required')
            ->assertJsonStructure(['two_factor', 'challenge_token', 'otpauth_uri', 'secret']);

        $this->assertStringStartsWith('otpauth://totp/', $response->json('otpauth_uri'));
        $this->assertSame(32, strlen($response->json('secret')));

        // Le secret est en attente tant qu'aucun code ne l'a confirmé.
        $this->assertNotNull($this->user->fresh()->two_factor_secret);
        $this->assertNull($this->user->fresh()->two_factor_confirmed_at);
        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());
    }

    public function test_login_asks_for_a_code_when_two_factor_is_already_enabled(): void
    {
        $this->enrol();

        $this->login()
            ->assertOk()
            ->assertJsonPath('two_factor', 'required')
            ->assertJsonMissingPath('secret')
            ->assertJsonMissingPath('otpauth_uri');
    }

    // --- Étape 2 : enrôlement ------------------------------------------------

    public function test_enrolment_completes_login_with_a_valid_code(): void
    {
        $response = $this->login();
        $secret = $response->json('secret');

        $this->postJson('/api/two-factor-setup', [
            'challenge_token' => $response->json('challenge_token'),
            'code' => $this->codeFor($secret),
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@exemple.com');

        $this->assertAuthenticatedAs($this->user->fresh());
        $this->assertNotNull($this->user->fresh()->two_factor_confirmed_at);
    }

    public function test_enrolment_rejects_a_wrong_code_and_stays_logged_out(): void
    {
        $response = $this->login();

        $this->postJson('/api/two-factor-setup', [
            'challenge_token' => $response->json('challenge_token'),
            'code' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertGuest();
        $this->assertNull($this->user->fresh()->two_factor_confirmed_at);
    }

    // --- Étape 2 : challenge -------------------------------------------------

    public function test_challenge_completes_login_with_a_valid_code(): void
    {
        $secret = $this->enrol();

        $response = $this->login();

        $this->postJson('/api/two-factor-challenge', [
            'challenge_token' => $response->json('challenge_token'),
            'code' => $this->codeFor($secret),
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@exemple.com');

        $this->assertAuthenticatedAs($this->user->fresh());
    }

    public function test_challenge_rejects_a_wrong_code(): void
    {
        $this->enrol();

        $response = $this->login();

        $this->postJson('/api/two-factor-challenge', [
            'challenge_token' => $response->json('challenge_token'),
            'code' => '123456',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertGuest();
    }

    public function test_challenge_rejects_a_forged_token(): void
    {
        $this->enrol();

        $this->postJson('/api/two-factor-challenge', [
            'challenge_token' => 'pas-un-jeton',
            'code' => '123456',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('challenge_token');

        $this->assertGuest();
    }

    public function test_challenge_rejects_an_expired_token(): void
    {
        $secret = $this->enrol();

        $expired = Crypt::encryptString(json_encode([
            'user_id' => $this->user->getKey(),
            'purpose' => 'two-factor',
            'expires_at' => now()->subMinute()->timestamp,
        ]));

        $this->postJson('/api/two-factor-challenge', [
            'challenge_token' => $expired,
            'code' => $this->codeFor($secret),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('challenge_token');

        $this->assertGuest();
    }

    // --- Fuite du secret -----------------------------------------------------

    public function test_the_secret_is_never_exposed_once_authenticated(): void
    {
        $secret = $this->enrol();
        $response = $this->login();

        $setup = $this->postJson('/api/two-factor-challenge', [
            'challenge_token' => $response->json('challenge_token'),
            'code' => $this->codeFor($secret),
        ])->assertOk();

        $setup->assertJsonMissingPath('user.two_factor_secret');

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonMissingPath('two_factor_secret')
            ->assertJsonMissingPath('password');
    }

    // --- Commande de secours -------------------------------------------------

    public function test_artisan_command_resets_two_factor(): void
    {
        $this->enrol();

        $this->artisan('user:disable-2fa', ['email' => 'admin@exemple.com'])
            ->assertSuccessful();

        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());

        // Le compte repasse par l'enrôlement.
        $this->login()->assertJsonPath('two_factor', 'setup_required');
    }

    public function test_artisan_command_fails_on_unknown_email(): void
    {
        $this->artisan('user:disable-2fa', ['email' => 'inconnu@exemple.com'])
            ->assertFailed();
    }

    /**
     * Met le compte dans l'état « TOTP actif » et renvoie le secret.
     */
    private function enrol(): string
    {
        $secret = app(TwoFactorAuthenticator::class)->generateSecret();

        $this->user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }
}
