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
