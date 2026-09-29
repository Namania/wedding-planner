<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
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

    /**
     * Le pilote de diffusion des tests est `null` : son `auth()` ne consulte
     * aucun canal et répond 200 à tout. Sans vrai diffuseur, un test HTTP sur
     * `broadcasting/auth` passerait au vert sans rien prouver.
     *
     * Les canaux sont enregistrés sur l'instance de diffuseur résolue au
     * démarrage : en changer en cours de test donne un diffuseur vierge, d'où
     * la relecture du fichier de canaux — c'est bien celui de production que
     * ces tests exercent. Les identifiants sont factices et aucune requête
     * n'atteint Reverb : un refus s'arrête avant toute signature de réponse.
     */
    private function useRealBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'cle-de-test',
            'broadcasting.connections.reverb.secret' => 'secret-de-test',
            'broadcasting.connections.reverb.app_id' => 'app-de-test',
            'broadcasting.connections.reverb.options.host' => 'reverb.invalid',
        ]);

        require base_path('routes/channels.php');
    }

    /**
     * Les deux tests ci-dessus invoquent les closures brutes rendues par
     * Broadcast::getChannels() : la route `broadcasting/auth` et sa liste de
     * gardes n'y sont jamais exercées. C'est exactement l'angle mort qui avait
     * laissé passer une perte de garde plus tôt dans ce chantier. Ces deux-ci
     * passent réellement par HTTP.
     */
    public function test_a_guest_is_refused_on_the_wedding_channel_over_http(): void
    {
        $this->useRealBroadcaster();

        $guest = User::factory()->guest()->create();

        $this->actingAs($guest)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-wedding',
            ])
            ->assertForbidden();
    }

    public function test_a_banned_guest_is_refused_on_the_gallery_channel_over_http(): void
    {
        $this->useRealBroadcaster();

        $banned = User::factory()->guest()->create(['banned_at' => now()]);

        $this->actingAs($banned)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-gallery',
            ])
            ->assertForbidden();
    }

    public function test_a_guest_is_never_pushed_towards_two_factor_enrolment(): void
    {
        $this->assertFalse(User::factory()->guest()->create()->requiresTwoFactor());
        $this->assertTrue(User::factory()->create()->requiresTwoFactor());
    }

    /**
     * `role` décide de tout ce qu'un compte peut faire : il ne doit jamais
     * pouvoir arriver par une charge utile de requête. Un futur
     * `User::create($request->validated())` donnerait sinon l'administration
     * du mariage à qui glisserait `role` dans son formulaire.
     */
    public function test_the_role_is_not_mass_assignable(): void
    {
        $user = new User([
            'name' => 'Curieux',
            'email' => 'curieux@exemple.com',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->assertNull($user->role);
    }

    public function test_the_role_has_no_default(): void
    {
        // Un compte créé sans rôle explicite doit échouer plutôt que de
        // devenir administrateur par inadvertance. On vérifie que l'échec
        // vient bien de la colonne role : sans ça, l'ajout futur d'une autre
        // colonne NOT NULL sans défaut ferait passer ce test pour la
        // mauvaise raison.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('role');

        User::forceCreate([
            'name' => 'Sans rôle',
            'email' => 'sans-role@exemple.com',
            'password' => 'password',
        ]);
    }
}
