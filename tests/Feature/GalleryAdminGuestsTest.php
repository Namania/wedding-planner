<?php

namespace Tests\Feature;

use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            ->patchJson('/api/gallery-admin/guests/'.$other->getKey().'/unban')
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

    /**
     * Le débannissement est un contrôle de modération qu'on utilise sous
     * pression le soir même, quand on s'aperçoit d'avoir banni le mauvais
     * invité. On vérifie la valeur en base, et pas seulement le 200 : un
     * retour à `update(['banned_at' => null])` serait ignoré en silence,
     * `banned_at` n'étant pas assignable en masse.
     */
    public function test_it_unbans_a_guest(): void
    {
        $guest = User::factory()->guest()->create();
        $guest->forceFill(['banned_at' => now()])->save();

        $this->actingAs($this->admin)
            ->patchJson('/api/gallery-admin/guests/'.$guest->getKey().'/unban')
            ->assertOk()
            ->assertJsonPath('banned_at', null);

        $this->assertNull($guest->fresh()->banned_at);
    }

    /**
     * Les photos restent masquées : le débannissement rend l'accès, pas la
     * visibilité de ce qui avait motivé le bannissement.
     */
    public function test_unbanning_leaves_the_photos_hidden(): void
    {
        $guest = User::factory()->guest()->create();
        GalleryPhoto::factory()->create(['user_id' => $guest->getKey()]);

        $this->actingAs($this->admin)
            ->patchJson('/api/gallery-admin/guests/'.$guest->getKey().'/ban')
            ->assertOk();

        $this->actingAs($this->admin)
            ->patchJson('/api/gallery-admin/guests/'.$guest->getKey().'/unban')
            ->assertOk();

        $this->assertNotNull($guest->photos()->sole()->hidden_at);
    }

    /**
     * Cette route supprime définitivement des lignes et des fichiers : seul le
     * 404 était couvert. On vérifie que le compte, ses photos et les fichiers
     * sur le disque disparaissent bien tous les trois.
     */
    public function test_it_deletes_a_guest_with_their_photos_and_files(): void
    {
        config(['gallery.disk' => 'local']);
        Storage::fake('local');

        $guest = User::factory()->guest()->create();
        $photo = GalleryPhoto::factory()->create(['user_id' => $guest->getKey()]);

        Storage::disk('local')->put($photo->path, 'jpeg');
        Storage::disk('local')->put($photo->thumb_path, 'jpeg');

        // Les photos d'un autre invité ne doivent pas partir avec.
        $other = User::factory()->guest()->create();
        $otherPhoto = GalleryPhoto::factory()->create(['user_id' => $other->getKey()]);
        Storage::disk('local')->put($otherPhoto->path, 'jpeg');

        $this->actingAs($this->admin)
            ->deleteJson('/api/gallery-admin/guests/'.$guest->getKey())
            ->assertOk();

        $this->assertNull($guest->fresh());
        $this->assertDatabaseMissing('gallery_photos', ['id' => $photo->getKey()]);
        Storage::disk('local')->assertMissing($photo->path);
        Storage::disk('local')->assertMissing($photo->thumb_path);

        $this->assertNotNull($other->fresh());
        $this->assertDatabaseHas('gallery_photos', ['id' => $otherPhoto->getKey()]);
        Storage::disk('local')->assertExists($otherPhoto->path);
    }
}
