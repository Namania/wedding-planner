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

    /**
     * La chaîne réelle en production compte plusieurs sauts : Cloudflare,
     * Traefik, puis nginx. Chacun ajoute son adresse à X-Forwarded-For. Faire
     * confiance au seul appelant direct s'arrêterait sur Traefik, et tout le
     * monde aurait la même IP — c'est ce que faisait `at: '*'`.
     */
    public function test_every_private_hop_of_the_chain_is_skipped(): void
    {
        Route::get('api/_test/ip', fn (Request $request) => ['ip' => $request->ip()]);

        $this->getJson('/api/_test/ip', ['X-Forwarded-For' => '203.0.113.42, 172.18.0.5, 10.0.1.7'])
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.42');
    }

    /**
     * Le contrepoint : on ne prend pas aveuglément l'adresse la plus à gauche,
     * qu'un client peut écrire lui-même. On s'arrête au premier saut qui n'est
     * pas un proxy de confiance.
     */
    public function test_a_public_hop_stops_the_walk(): void
    {
        Route::get('api/_test/ip', fn (Request $request) => ['ip' => $request->ip()]);

        $this->getJson('/api/_test/ip', ['X-Forwarded-For' => '203.0.113.42, 198.51.100.9'])
            ->assertOk()
            ->assertJsonPath('ip', '198.51.100.9');
    }
}
