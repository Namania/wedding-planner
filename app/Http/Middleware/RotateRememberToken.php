<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fait tourner le jeton « remember me » à chaque fois qu'il sert.
 *
 * Sans cette rotation, la découpe session courte / cookie long n'apporte rien :
 * Laravel ne renouvelle ce jeton qu'à la déconnexion, donc un cookie volé
 * vaudrait une semaine d'accès — exactement comme une session d'une semaine.
 * Avec elle, il ne vaut qu'une seule réutilisation.
 */
class RotateRememberToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Garde explicitement 'web' : après auth:sanctum, la garde par défaut
        // devient 'sanctum' (Authenticate::authenticate() appelle shouldUse()),
        // dont le RequestGuard n'a pas de viaRemember(). C'est la garde de
        // session qui sait si le cookie remember a servi.
        //
        // viaRemember() n'est vrai que sur la requête où le cookie a ressuscité
        // la session ; les suivantes passent par la session elle-même. La
        // rotation a donc lieu une fois par reconnexion, pas à chaque requête.
        if (Auth::guard('web')->viaRemember() && ($user = Auth::guard('web')->user()) !== null) {
            $user->forceFill(['remember_token' => Str::random(60)])->save();

            // Réémet le cookie avec le nouveau jeton : sans cela l'ancien
            // resterait valable jusqu'à son expiration.
            Auth::guard('web')->login($user, remember: true);
        }

        return $next($request);
    }
}
