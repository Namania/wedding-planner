<?php

namespace Tests\Feature;

use App\Models\GalleryPhoto;
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
            // Pas de clé 'data' ici : JsonResource::withoutWrapping() est actif
            // globalement (AppServiceProvider) — seules les réponses paginées
            // (comme /api/gallery/photos) gardent cette enveloppe.
            ->assertJsonCount(2);
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
        GalleryPhoto::factory()->create(['user_id' => $guest->getKey()]);

        $this->actingAs($this->admin)
            ->patchJson('/api/gallery-admin/guests/'.$guest->getKey().'/ban')
            ->assertOk();

        $this->assertNotNull($guest->fresh()->banned_at);
        $this->assertNotNull($guest->photos()->sole()->hidden_at);
    }
}
