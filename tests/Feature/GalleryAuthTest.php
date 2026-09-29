<?php

namespace Tests\Feature;

use App\Models\GallerySettings;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

class GalleryAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // L'inscription et la connexion ouvrent désormais une session (et non
        // plus un jeton Bearer) : sans domaine stateful déclaré auprès de
        // Sanctum, EnsureFrontendRequestsAreStateful n'insère pas le groupe de
        // middlewares « web » (StartSession compris) sur ces routes API, et
        // tout appel à $request->session() échoue.
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');

        $this->token = str_repeat('a', 64);

        GallerySettings::current()->update(['invite_token' => $this->token]);
        Wedding::create([
            'spouse_1_name' => 'MADAME',
            'spouse_2_name' => 'MONSIEUR',
            'date' => '2028-01-01',
        ]);
    }

    private function register(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/gallery/register', array_merge([
            'token' => $this->token,
            'name' => 'Camille D.',
            'email' => 'camille@exemple.com',
            'password' => 'motdepasse',
        ], $overrides));
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

    public function test_registration_creates_a_guest_account_and_opens_a_session(): void
    {
        $response = $this->register()->assertCreated()
            ->assertJsonPath('guest.name', 'Camille D.');

        $user = User::where('email', 'camille@exemple.com')->sole();

        $this->assertSame(User::ROLE_GUEST, $user->role);
        $this->assertTrue(Hash::check('motdepasse', $user->password));

        $recaller = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNotNull($recaller, 'La session invité doit être ouverte avec un cookie de reconnexion.');
    }

    public function test_registration_requires_valid_invite_token(): void
    {
        $this->register(['token' => str_repeat('b', 64)])->assertNotFound();
    }

    public function test_registration_rejected_when_closed(): void
    {
        GallerySettings::current()->update(['registrations_open' => false]);

        $this->register()->assertUnprocessable();
    }

    public function test_registration_rejected_when_max_guests_reached(): void
    {
        GallerySettings::current()->update(['max_guests' => 1]);
        User::factory()->guest()->create();

        $this->register()->assertUnprocessable();
    }

    public function test_registration_rejects_an_email_already_taken(): void
    {
        User::factory()->create(['email' => 'maries@exemple.com']);

        $this->register(['email' => 'maries@exemple.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_a_password_of_eight_characters(): void
    {
        $this->register(['password' => 'court'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_login_with_email_and_password(): void
    {
        $this->register();
        $this->flushSession();
        Auth::forgetGuards();

        $this->postJson('/api/gallery/login', [
            'email' => 'camille@exemple.com',
            'password' => 'motdepasse',
        ])->assertOk()->assertJsonPath('guest.name', 'Camille D.');
    }

    /**
     * Même message que pour un mot de passe faux ou un email inconnu : les
     * distinguer révélerait qu'un compte banni existe pour cette adresse.
     */
    public function test_login_rejects_a_banned_guest_account(): void
    {
        User::factory()->guest()->create([
            'email' => 'camille@exemple.com',
            'password' => 'motdepasse',
        ])->forceFill(['banned_at' => now()])->save();

        $this->postJson('/api/gallery/login', [
            'email' => 'camille@exemple.com',
            'password' => 'motdepasse',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_an_admin_cannot_log_in_through_the_guest_route(): void
    {
        User::factory()->create(['email' => 'maries@exemple.com', 'password' => 'motdepasse']);

        $this->postJson('/api/gallery/login', [
            'email' => 'maries@exemple.com',
            'password' => 'motdepasse',
        ])->assertStatus(422);
    }

    public function test_a_guest_banned_mid_session_is_refused_on_the_next_request(): void
    {
        $this->register();

        // Sans ce vidage, la garde 'web' resterait ici sur l'instance en
        // mémoire retournée par le contrôleur d'inscription — encore marquée
        // wasRecentlyCreated — et ResourceResponse en déduirait un 201 pour
        // cette requête de lecture au lieu du 200 attendu.
        Auth::forgetGuards();

        $this->getJson('/api/gallery/me')->assertOk();

        User::where('email', 'camille@exemple.com')->sole()
            ->forceFill(['banned_at' => now()])->save();

        // La garde met l'utilisateur résolu en cache entre deux requêtes d'un
        // même test : sans ce vidage, la seconde requête relirait l'instance
        // d'avant le bannissement et le test passerait pour rien.
        Auth::forgetGuards();

        $this->getJson('/api/gallery/me')->assertForbidden();
    }

    public function test_a_guest_cannot_use_the_admin_api(): void
    {
        $this->register();

        $this->getJson('/api/guests')->assertForbidden();
    }

    public function test_an_admin_cannot_use_the_guest_gallery_routes(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/gallery/me')
            ->assertForbidden();
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

    /**
     * Le Critical relevé sur la tâche 2 : avant cette tâche, un invité
     * envoyait des photos en tant que GalleryGuest, dont les identifiants
     * vivent dans un espace disjoint de celui de gallery_photos.user_id
     * (désormais une clé vers users.id). Une coïncidence d'identifiants
     * pouvait attribuer silencieusement la photo à un autre compte. Cette
     * tâche fait de l'inscription un vrai compte User authentifié par
     * session : ce test enchaîne inscription et envoi pour le prouver.
     */
    #[RequiresPhpExtension('gd')]
    public function test_a_freshly_registered_guest_uploads_a_photo_under_their_own_account(): void
    {
        config(['gallery.disk' => 'local']);
        Storage::fake('local');

        $registration = $this->register()->assertCreated();
        $guestId = $registration->json('guest.id');

        // Un autre compte invité, créé après coup : si l'identifiant de la
        // photo coïncidait par accident avec un autre id, ce serait le signe
        // du bug — la photo n'appartient qu'au compte qui vient de s'inscrire.
        $other = User::factory()->guest()->create();
        $this->assertNotSame($other->id, $guestId);

        $upload = $this->postJson('/api/gallery/photos', [
            'photo' => UploadedFile::fake()->image('mariage.jpg', 2000, 1500),
        ])->assertCreated();

        $this->assertSame($guestId, $upload->json('guest_id'));
        $this->assertDatabaseHas('gallery_photos', [
            'id' => $upload->json('id'),
            'user_id' => $guestId,
        ]);
    }
}
