<?php

namespace Tests\Feature;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrustedDeviceRegistryTest extends TestCase
{
    use RefreshDatabase;

    private TrustedDeviceRegistry $registry;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(TrustedDeviceRegistry::class);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@exemple.com',
            'password' => 'password',
        ]);
    }

    private function request(string $agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/130.0 Safari/537.36'): Request
    {
        return Request::create('/api/login', 'POST', server: [
            'HTTP_USER_AGENT' => $agent,
            'REMOTE_ADDR' => '203.0.113.7',
        ]);
    }

    public function test_it_issues_a_cookie_whose_token_is_stored_hashed(): void
    {
        $cookie = $this->registry->issueFor($this->user, $this->request());

        $device = TwoFactorTrustedDevice::sole();

        $this->assertSame(TrustedDeviceRegistry::COOKIE, $cookie->getName());
        $this->assertNotEmpty($cookie->getValue());

        // Le jeton en clair ne doit jamais toucher la base.
        $this->assertNotSame($cookie->getValue(), $device->token_hash);
        $this->assertSame(hash('sha256', $cookie->getValue()), $device->token_hash);
    }

    public function test_it_records_the_device_context(): void
    {
        $this->registry->issueFor($this->user, $this->request());

        $device = TwoFactorTrustedDevice::sole();

        $this->assertSame($this->user->getKey(), $device->user_id);
        $this->assertSame('Chrome sur macOS', $device->name);
        $this->assertSame('203.0.113.7', $device->ip_address);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $device->expires_at->timestamp, 60);
    }

    public function test_it_finds_a_valid_device_from_its_plain_token(): void
    {
        $cookie = $this->registry->issueFor($this->user, $this->request());

        $this->assertNotNull($this->registry->findValidFor($this->user, $cookie->getValue()));
    }

    public function test_it_rejects_an_expired_device(): void
    {
        $cookie = $this->registry->issueFor($this->user, $this->request());

        TwoFactorTrustedDevice::sole()->forceFill(['expires_at' => now()->subDay()])->save();

        $this->assertNull($this->registry->findValidFor($this->user, $cookie->getValue()));
    }

    public function test_it_rejects_a_device_belonging_to_another_account(): void
    {
        $cookie = $this->registry->issueFor($this->user, $this->request());

        $other = User::create([
            'name' => 'Autre',
            'email' => 'autre@exemple.com',
            'password' => 'password',
        ]);

        $this->assertNull($this->registry->findValidFor($other, $cookie->getValue()));
    }

    public function test_it_rejects_an_unknown_or_empty_token(): void
    {
        $this->assertNull($this->registry->findValidFor($this->user, null));
        $this->assertNull($this->registry->findValidFor($this->user, ''));
        $this->assertNull($this->registry->findValidFor($this->user, 'jeton-inventé'));
    }

    public function test_it_falls_back_to_a_readable_label_for_unknown_agents(): void
    {
        $this->registry->issueFor($this->user, $this->request(''));

        $this->assertSame('Appareil inconnu', TwoFactorTrustedDevice::sole()->name);
    }
}
