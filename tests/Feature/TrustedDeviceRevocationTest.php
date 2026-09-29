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

        // withCredentials : les helpers *Json() du client de test n'attachent
        // aucun cookie sans lui (prepareCookiesForJsonRequest() renvoie un
        // tableau vide sinon), alors que le cookie d'appareil de confiance est
        // justement posé ici via withCookie.
        $response = $this->actingAs($this->user)
            ->withCookie(TrustedDeviceRegistry::COOKIE, $current)
            ->withCredentials()
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
            ->withCredentials()
            ->deleteJson('/api/two-factor/devices/'.$device->getKey())
            ->assertOk();

        $this->assertSame(0, TwoFactorTrustedDevice::count());

        $cleared = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === TrustedDeviceRegistry::COOKIE);

        $this->assertNotNull($cleared, 'La réponse doit effacer le cookie de l\'appareil courant.');

        // Pas de comparaison sur la valeur : EncryptCookies rechiffre TOUT
        // cookie sortant, y compris celui-ci — sa valeur n'est donc jamais
        // vide ou nulle une fois la réponse passée dans le pipeline. Ce qui
        // fait réellement disparaître le cookie côté navigateur, c'est son
        // expiration dans le passé ; isCleared() est le test exact de Symfony
        // pour ça.
        $this->assertTrue(
            $cleared->isCleared(),
            'Le cookie effacé doit avoir une date d\'expiration dans le passé.',
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

    /**
     * Amendement : une session déjà ouverte depuis un appareil survit à sa
     * révocation dans la table des appareils de confiance, et se rouvre
     * toute seule pendant une semaine grâce au cookie « remember me ». Faire
     * tourner remember_token ici invalide ce cookie sur tous les appareils :
     * c'est la seule chose qui coupe réellement les reconnexions silencieuses
     * après la perte d'un ordinateur.
     */
    public function test_revoking_every_device_also_rotates_the_remember_token(): void
    {
        $this->issueDevice();
        $this->issueDevice('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/605.1');

        $previousToken = $this->user->remember_token;

        $this->actingAs($this->user)
            ->deleteJson('/api/two-factor/devices')
            ->assertOk();

        $newToken = $this->user->fresh()->remember_token;

        $this->assertNotNull($newToken);
        $this->assertNotSame($previousToken, $newToken);
        $this->assertSame(60, strlen($newToken));
    }

    /**
     * Contraste avec le test précédent : révoquer un seul appareil ne
     * concerne que les connexions futures depuis cet appareil-là, il ne doit
     * pas couper les sessions « remember me » des autres appareils.
     */
    public function test_revoking_a_single_device_does_not_rotate_the_remember_token(): void
    {
        $token = $this->issueDevice();
        $device = TwoFactorTrustedDevice::sole();

        $previousToken = $this->user->remember_token;

        $this->actingAs($this->user)
            ->withCookie(TrustedDeviceRegistry::COOKIE, $token)
            ->withCredentials()
            ->deleteJson('/api/two-factor/devices/'.$device->getKey())
            ->assertOk();

        $this->assertSame($previousToken, $this->user->fresh()->remember_token);
    }

    public function test_the_routes_require_authentication(): void
    {
        $this->getJson('/api/two-factor/devices')->assertUnauthorized();
    }
}
