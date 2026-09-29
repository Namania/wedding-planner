<?php

namespace Tests\Feature;

use App\Models\GallerySettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GalleryRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La connexion ouvre une session : sans domaine stateful déclaré,
        // Sanctum n'insère pas StartSession sur les routes API.
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');
    }

    private function attemptLogin(string $email, string $password, string $ip): TestResponse
    {
        return $this->postJson('/api/gallery/login', [
            'email' => $email,
            'password' => $password,
        ], ['X-Forwarded-For' => $ip]);
    }

    /**
     * Le seau indexé sur l'adresse tentée doit rester propre à chaque compte.
     * Indexé sur un champ absent de la requête, il deviendrait une clé vide et
     * donc identique pour tous : dix connexions invité par minute pour
     * l'application entière, toutes adresses et toutes IP confondues.
     */
    public function test_two_guests_do_not_share_the_same_login_bucket(): void
    {
        User::factory()->guest()->create([
            'email' => 'camille@exemple.com',
            'password' => 'motdepasse',
        ]);

        User::factory()->guest()->create([
            'email' => 'dominique@exemple.com',
            'password' => 'motdepasse',
        ]);

        // Dix tentatives ratées sur l'adresse de Camille, réparties sur deux
        // IP : le seau par IP (5 par minute) buterait le premier sans cela, et
        // ce test ne prouverait plus rien du seau par adresse.
        foreach (['10.0.0.1', '10.0.0.2'] as $ip) {
            for ($i = 0; $i < 5; $i++) {
                $this->attemptLogin('camille@exemple.com', 'mauvais-mot-de-passe', $ip)
                    ->assertStatus(422);
            }
        }

        // Le onzième essai sur cette adresse est refusé, depuis une IP neuve :
        // le seau plein est bien celui de l'adresse.
        $this->attemptLogin('camille@exemple.com', 'motdepasse', '10.0.0.3')
            ->assertStatus(429);

        // Dominique, depuis cette même IP, doit malgré tout se connecter : les
        // déboires de Camille ne sont pas les siens.
        $this->attemptLogin('dominique@exemple.com', 'motdepasse', '10.0.0.3')
            ->assertOk()
            ->assertJsonPath('guest.email', 'dominique@exemple.com');
    }

    /**
     * Cent invités derrière le même Wi-Fi partagent une seule IP publique :
     * un plafond horaire de dix inscriptions y laisserait le onzième arrivant
     * sans compte et sans recours.
     */
    public function test_a_shared_ip_can_register_more_than_ten_guests_in_an_hour(): void
    {
        $token = str_repeat('a', 64);
        GallerySettings::current()->update([
            'invite_token' => $token,
            'max_guests' => 500,
        ]);

        for ($i = 1; $i <= 11; $i++) {
            $this->postJson('/api/gallery/register', [
                'token' => $token,
                'name' => 'Invité '.$i,
                'email' => 'invite'.$i.'@exemple.com',
                'password' => 'motdepasse',
            ], ['X-Forwarded-For' => '203.0.113.7'])->assertCreated();

            // Chaque inscription ouvre une session : sans ce vidage, la
            // suivante repartirait de celle du précédent invité.
            $this->flushSession();
            Auth::forgetGuards();
        }

        $this->assertSame(11, User::where('role', User::ROLE_GUEST)->count());
    }
}
