<?php

namespace Tests\Feature;

use App\Models\GalleryGuest;
use App\Models\GallerySettings;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GalleryAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = str_repeat('a', 64);

        GallerySettings::current()->update(['invite_token' => $this->token]);
        Wedding::create([
            'spouse_1_name' => 'MADAME',
            'spouse_2_name' => 'MONSIEUR',
            'date' => '2028-01-01',
        ]);
    }

    public function test_invite_check_rejects_invalid_token(): void
    {
        $this->getJson('/api/gallery/invite/'.str_repeat('b', 64))
            ->assertNotFound();
    }

    public function test_invite_check_returns_wedding_info_for_valid_token(): void
    {
        $this->getJson('/api/gallery/invite/'.$this->token)
            ->assertOk()
            ->assertJsonPath('wedding.spouse_1_name', 'MADAME')
            ->assertJsonPath('registrations_open', true);
    }

    public function test_registration_creates_account_and_returns_session_token(): void
    {
        $response = $this->postJson('/api/gallery/register', [
            'token' => $this->token,
            'name' => 'Camille',
            'pin' => '4821',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'guest' => ['id', 'name']]);

        $this->assertDatabaseHas('gallery_guests', ['name_normalized' => 'camille']);
    }

    public function test_registration_requires_valid_invite_token(): void
    {
        $this->postJson('/api/gallery/register', [
            'token' => str_repeat('b', 64),
            'name' => 'Camille',
            'pin' => '4821',
        ])->assertNotFound();
    }

    public function test_registration_rejected_when_closed(): void
    {
        GallerySettings::current()->update(['registrations_open' => false]);

        $this->postJson('/api/gallery/register', [
            'token' => $this->token,
            'name' => 'Camille',
            'pin' => '4821',
        ])->assertUnprocessable();
    }

    public function test_registration_rejected_when_max_guests_reached(): void
    {
        GallerySettings::current()->update(['max_guests' => 1]);
        GalleryGuest::factory()->create();

        $this->postJson('/api/gallery/register', [
            'token' => $this->token,
            'name' => 'Camille',
            'pin' => '4821',
        ])->assertUnprocessable();
    }

    public function test_name_is_unique_case_insensitively(): void
    {
        GalleryGuest::factory()->create([
            'name' => 'Camille',
            'name_normalized' => 'camille',
        ]);

        $this->postJson('/api/gallery/register', [
            'token' => $this->token,
            'name' => '  CAMILLE ',
            'pin' => '4821',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_login_with_name_and_pin(): void
    {
        GalleryGuest::factory()->create([
            'name' => 'Camille',
            'name_normalized' => 'camille',
        ]);

        $this->postJson('/api/gallery/login', ['name' => 'Camille', 'pin' => '1234'])
            ->assertOk()
            ->assertJsonStructure(['token', 'guest']);

        $this->postJson('/api/gallery/login', ['name' => 'Camille', 'pin' => '0000'])
            ->assertUnprocessable();
    }

    public function test_banned_guest_cannot_login_nor_use_api(): void
    {
        $guest = GalleryGuest::factory()->banned()->create();

        $this->postJson('/api/gallery/login', ['name' => $guest->name, 'pin' => '1234'])
            ->assertUnprocessable();

        Sanctum::actingAs($guest);
        $this->getJson('/api/gallery/photos')->assertForbidden();
    }

    public function test_gallery_guest_token_cannot_access_admin_api(): void
    {
        Sanctum::actingAs(GalleryGuest::factory()->create());

        $this->getJson('/api/guests')->assertForbidden();
        $this->getJson('/api/budget')->assertForbidden();
        $this->getJson('/api/gallery-admin/settings')->assertForbidden();
    }

    public function test_admin_session_cannot_use_guest_gallery_routes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/gallery/me')->assertForbidden();
    }

    public function test_admin_can_read_settings_and_rotate_token(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/gallery-admin/settings')
            ->assertOk()
            ->assertJsonPath('invite_token', $this->token);

        $this->postJson('/api/gallery-admin/settings/rotate-token')->assertOk();

        $this->assertNotSame($this->token, GallerySettings::current()->invite_token);
    }
}
