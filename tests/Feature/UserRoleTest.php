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

    public function test_a_guest_is_never_pushed_towards_two_factor_enrolment(): void
    {
        $this->assertFalse(User::factory()->guest()->create()->requiresTwoFactor());
        $this->assertTrue(User::factory()->create()->requiresTwoFactor());
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
