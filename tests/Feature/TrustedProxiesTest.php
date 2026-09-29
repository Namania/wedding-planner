<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    /**
     * En production, tout passe par le proxy de Dokploy : sans confiance aux
     * proxys, $request->ip() renverrait l'adresse interne du proxy pour tout
     * le monde. L'IP affichée par l'écran de révocation des appareils serait
     * identique partout, et les limitations de débit indexées sur l'IP
     * seraient en fait globales.
     */
    public function test_the_forwarded_ip_is_the_one_seen_by_the_application(): void
    {
        // Sous le préfixe api/ : la route attrape-tout de routes/web.php, qui
        // sert le SPA, capterait n'importe quelle autre URL avant celle-ci.
        Route::get('api/_test/ip', fn (Request $request) => ['ip' => $request->ip()]);

        $this->getJson('/api/_test/ip', ['X-Forwarded-For' => '203.0.113.42'])
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.42');
    }
}
