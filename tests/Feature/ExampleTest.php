<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Smoke test : l'application démarre et route correctement.
     *
     * On interroge la racine de l'API plutôt que "/" : cette dernière sert le
     * SPA compilé (public/build/index.html), absent tant que le front n'a pas
     * été buildé — ce qui n'est pas le cas en CI ni en dev.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->getJson('/api/')
            ->assertOk()
            ->assertJsonPath('status', 'Up and running');
    }
}
