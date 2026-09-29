<?php

use App\Http\Middleware\EnsureAdminUser;
use App\Http\Middleware\EnsureGalleryGuest;
use App\Http\Middleware\PreferAccessToken;
use App\Http\Middleware\RotateRememberToken;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
        // Derrière le Traefik de Dokploy, les conteneurs ne sont joignables que
        // par le réseau interne : compose.prod.yml les déclare en `expose`
        // seul, jamais en `ports`. Aucune requête ne peut donc atteindre
        // l'application sans passer par le proxy, et faire confiance à tous les
        // proxys revient à faire confiance à ce seul proxy — dont l'adresse
        // change à chaque redéploiement, ce qui rendrait une liste blanche
        // d'IP illusoire à maintenir.
        //
        // Sans cela, $request->ip() renvoie l'adresse interne du proxy, la même
        // pour tout le monde : la colonne IP de l'écran de révocation des
        // appareils n'aurait plus aucune valeur, et toutes les limitations de
        // débit indexées sur l'IP (connexion admin, 2FA, galerie) seraient en
        // réalité globales — un seul visiteur pourrait verrouiller tout le
        // monde.
        //
        // Ce réglage serait dangereux si les conteneurs étaient exposés
        // directement : n'importe qui pourrait alors envoyer un en-tête
        // X-Forwarded-For de son choix, se donner une IP différente à chaque
        // requête pour contourner les limitations de débit, brouiller les
        // journaux et falsifier l'IP enregistrée avec un appareil de confiance.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'api/gallery/*',
            'broadcasting/auth',
        ]);

        // Laravel retrie les middlewares de route selon une liste de priorité,
        // dans laquelle `auth:sanctum` (Authenticate, qui implémente
        // AuthenticatesRequests) figure. Le déclarer avant lui dans le groupe
        // de routes ne suffit donc pas : sans cette ligne, l'authentification
        // aurait déjà résolu — et mis en cache — l'utilisateur de la session
        // avant que prefer.token n'ait pu dire son mot.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: PreferAccessToken::class,
        );

        $middleware->alias([
            'admin.user' => EnsureAdminUser::class,
            'gallery.guest' => EnsureGalleryGuest::class,
            'prefer.token' => PreferAccessToken::class,
            'remember.rotate' => RotateRememberToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
