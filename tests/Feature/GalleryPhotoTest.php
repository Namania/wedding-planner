<?php

namespace Tests\Feature;

use App\Models\GalleryGuest;
use App\Models\GalleryPhoto;
use App\Models\GallerySettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

class GalleryPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gallery.disk' => 'local']);
        Storage::fake('local');
    }

    public function test_gallery_index_only_returns_visible_photos(): void
    {
        $guest = GalleryGuest::factory()->create();
        GalleryPhoto::factory()->for($guest, 'guest')->count(2)->create();
        GalleryPhoto::factory()->for($guest, 'guest')->hidden()->create();

        Sanctum::actingAs($guest);

        $this->getJson('/api/gallery/photos')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_gallery_requires_authentication(): void
    {
        $this->getJson('/api/gallery/photos')->assertUnauthorized();
    }

    #[RequiresPhpExtension('gd')]
    public function test_guest_can_upload_a_photo(): void
    {
        $guest = GalleryGuest::factory()->create();
        Sanctum::actingAs($guest);

        $response = $this->postJson('/api/gallery/photos', [
            'photo' => UploadedFile::fake()->image('mariage.jpg', 3000, 2000),
            'caption' => 'Ouverture du bal',
        ]);

        $response->assertCreated()
            ->assertJsonPath('guest_name', $guest->name)
            ->assertJsonPath('caption', 'Ouverture du bal');

        $photo = GalleryPhoto::first();

        $this->assertLessThanOrEqual(config('gallery.max_edge_full'), $photo->width);
        Storage::disk('local')->assertExists($photo->path);
        Storage::disk('local')->assertExists($photo->thumb_path);
    }

    #[RequiresPhpExtension('gd')]
    public function test_upload_rejected_beyond_quota(): void
    {
        GallerySettings::current()->update(['max_photos_per_guest' => 1]);

        $guest = GalleryGuest::factory()->create();
        GalleryPhoto::factory()->for($guest, 'guest')->create();

        Sanctum::actingAs($guest);

        $this->postJson('/api/gallery/photos', [
            'photo' => UploadedFile::fake()->image('mariage.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_upload_rejects_non_image_files(): void
    {
        Sanctum::actingAs(GalleryGuest::factory()->create());

        $this->postJson('/api/gallery/photos', [
            'photo' => UploadedFile::fake()->create('malware.php', 100, 'text/php'),
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_guest_can_only_delete_own_photos(): void
    {
        $owner = GalleryGuest::factory()->create();
        $other = GalleryGuest::factory()->create();
        $photo = GalleryPhoto::factory()->for($owner, 'guest')->create();

        Sanctum::actingAs($other);
        $this->deleteJson("/api/gallery/photos/{$photo->id}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/gallery/photos/{$photo->id}")->assertOk();

        $this->assertDatabaseMissing('gallery_photos', ['id' => $photo->id]);
    }

    public function test_photo_file_requires_valid_signature(): void
    {
        $photo = GalleryPhoto::factory()->create();

        $this->get("/api/gallery/photos/{$photo->id}/thumb")->assertForbidden();
    }

    public function test_admin_can_hide_photo_and_it_disappears_from_gallery(): void
    {
        $photo = GalleryPhoto::factory()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/gallery-admin/photos/{$photo->id}/hide")
            ->assertOk()
            ->assertJsonPath('hidden', true);

        Sanctum::actingAs(GalleryGuest::factory()->create());
        $this->getJson('/api/gallery/photos')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_banning_guest_hides_all_their_photos(): void
    {
        $guest = GalleryGuest::factory()->create();
        GalleryPhoto::factory()->for($guest, 'guest')->count(3)->create();

        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/gallery-admin/guests/{$guest->id}/ban")->assertOk();

        $this->assertTrue($guest->fresh()->isBanned());
        $this->assertSame(0, GalleryPhoto::visible()->count());
    }
}
