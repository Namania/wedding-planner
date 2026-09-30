<?php

use App\Http\Middleware\EnsureAdminUser;
use App\Http\Middleware\EnsureGalleryGuest;
use App\Http\Middleware\RotateRememberToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Toute la chaîne de proxys en production est privée : Traefik (Dokploy)
        // puis nginx (image front). Chacun ajoute son adresse à X-Forwarded-For.
        //
        // `at: '*'` ne ferait confiance qu'à l'appelant direct — nginx — et la
        // remontée s'arrêterait sur Traefik : tout le monde aurait la même IP, les
        // seaux de débit indexés dessus seraient globaux, et un seul visiteur
        // pourrait verrouiller la connexion admin. En listant les plages privées,
        // tous les sauts internes sont sautés et on s'arrête sur la première
        // adresse publique, celle du client.
        //
        // Ce réglage n'est sûr que parce que les conteneurs ne sont joignables que
        // par le réseau interne (`expose` seul dans compose.prod.yml) : un client
        // direct pourrait sinon forger X-Forwarded-For.
        $middleware->trustProxies(at: ['127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'], headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
        ]);

        $middleware->alias([
            'admin.user' => EnsureAdminUser::class,
            'gallery.guest' => EnsureGalleryGuest::class,
            'remember.rotate' => RotateRememberToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
