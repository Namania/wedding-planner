<?php

namespace Tests\Feature;

use App\Http\Middleware\RotateRememberToken;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class RememberSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@exemple.com',
            'password' => 'password',
        ]);
    }

    public function test_reconnecting_through_the_remember_cookie_rotates_the_token(): void
    {
        $this->user->forceFill(['remember_token' => 'jeton-initial'])->save();

        $recaller = $this->user->getKey().'|jeton-initial|'.$this->user->getAuthPassword();

        // Aucune session : seul le cookie remember peut authentifier. C'est
        // exactement la situation d'un retour après cinq minutes d'inactivité.
        //
        // withCookie et non withUnencryptedCookie : Sanctum applique
        // EncryptCookies aux requêtes venant d'un domaine stateful, donc le
        // cookie doit arriver chiffré pour être lu. withCookie s'en charge.
        //
        // withCredentials : les helpers *Json() du client de test n'attachent
        // les cookies à la requête que si on l'appelle explicitement (même
        // logique que withCredentials sur une XHR cross-origin réelle).
        $response = $this->withCredentials()
            ->withCookie('remember_web_'.sha1(SessionGuard::class), $recaller)
            ->getJson('/api/user');

        $response->assertOk();

        $this->assertNotSame(
            'jeton-initial',
            $this->user->fresh()->getRememberToken(),
            'Le jeton remember doit tourner à chaque réutilisation du cookie.',
        );
    }

    public function test_the_old_remember_cookie_stops_working_after_rotation(): void
    {
        $this->user->forceFill(['remember_token' => 'jeton-initial'])->save();

        $name = 'remember_web_'.sha1(SessionGuard::class);
        $recaller = $this->user->getKey().'|jeton-initial|'.$this->user->getAuthPassword();

        $this->withCredentials()->withCookie($name, $recaller)->getJson('/api/user')->assertOk();

        // La session ouverte par la première requête persiste d'un appel de
        // test à l'autre : sans ce vidage, la seconde requête s'authentifierait
        // par la session et ne prouverait rien sur le cookie.
        //
        // forgetGuards() : l'AuthManager mémorise aussi l'instance de la garde
        // 'web' elle-même (son utilisateur résolu inclus) d'un appel HTTP de
        // test à l'autre, puisque le conteneur n'est pas réinitialisé entre
        // deux requêtes d'un même test. Sans ça, la seconde requête verrait
        // l'utilisateur déjà résolu par la première, quel que soit le cookie
        // envoyé, et ne prouverait rien non plus.
        $this->flushSession();
        Auth::forgetGuards();

        // withCookie garde la valeur d'origine : c'est bien l'ancien cookie,
        // celui qu'un attaquant aurait volé, qu'on rejoue ici.
        $this->withCredentials()->withCookie($name, $recaller)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_a_losing_concurrent_request_does_not_reissue_a_cookie_nor_overwrite_the_winners_token(): void
    {
        // Une vraie course entre requêtes simultanées n'est pas reproductible
        // avec ce client de test synchrone : les requêtes s'exécutent l'une
        // après l'autre, jamais en parallèle. On reconstitue donc directement
        // la situation qu'une course produirait plutôt que la course elle-même.
        $this->user->forceFill(['remember_token' => 'jeton-perdant'])->save();

        // Instantané en mémoire de l'utilisateur tel que NOTRE requête l'aurait
        // résolu via le cookie remember, avant que la requête concurrente ne
        // gagne la course : son jeton en mémoire est encore l'ancien.
        $staleUser = $this->user->fresh();

        // La requête concurrente « gagne » : elle tourne le jeton en base
        // avant que notre middleware n'exécute son propre UPDATE conditionnel.
        $this->user->forceFill(['remember_token' => 'jeton-gagnant'])->save();

        // On appelle le middleware directement, en substituant à la garde
        // 'web' un mock qui rejoue exactement ce que verrait notre requête :
        // viaRemember() vrai, et un utilisateur dont le jeton en mémoire ne
        // correspond plus à celui de la base. shouldNotReceive('login') est
        // l'assertion centrale : c'est là, et seulement là, qu'un cookie
        // remember serait mis en file pour la réponse ; si la branche
        // perdante appelait login(), ce test échouerait sur ce point avant
        // même d'atteindre les asserts ci-dessous.
        $guard = Mockery::mock(StatefulGuard::class);
        $guard->shouldReceive('viaRemember')->once()->andReturn(true);
        $guard->shouldReceive('user')->once()->andReturn($staleUser);
        $guard->shouldNotReceive('login');

        Auth::shouldReceive('guard')->with('web')->andReturn($guard);

        $response = (new RotateRememberToken)->handle(
            Request::create('/api/user', 'GET'),
            fn ($request) => response('ok'),
        );

        $this->assertSame('ok', $response->getContent());

        // La valeur en base reste celle de la gagnante : la branche perdante
        // n'a rien réécrit.
        $this->assertSame(
            'jeton-gagnant',
            $this->user->fresh()->getRememberToken(),
            'Le jeton de la requête gagnante ne doit pas être écrasé par la perdante.',
        );
    }
}
