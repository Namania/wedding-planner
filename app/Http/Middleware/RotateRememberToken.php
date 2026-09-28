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
            // Le SPA peut envoyer plusieurs requêtes en parallèle avec le même
            // cookie remember (plusieurs widgets d'un tableau de bord, par
            // exemple) : chacune verrait viaRemember() vrai et tenterait sa
            // propre rotation. On mémorise donc le jeton lu par CETTE requête
            // avant de le remplacer, pour pouvoir détecter s'il a déjà bougé.
            $oldToken = $user->getRememberToken();
            $newToken = Str::random(60);

            // UPDATE conditionnel : la clause WHERE filtre aussi sur l'ancienne
            // valeur du jeton, pas seulement sur l'utilisateur. Si une requête
            // concurrente a déjà tourné le jeton entre notre lecture et cet
            // UPDATE, la ligne ne correspond plus à $oldToken et rien n'est
            // affecté : c'est ainsi qu'on détecte la course sans verrou explicite.
            $affectedRows = $user->newQuery()
                ->where($user->getKeyName(), $user->getKey())
                ->where($user->getRememberTokenName(), $oldToken)
                ->update([$user->getRememberTokenName() => $newToken]);

            if ($affectedRows === 0) {
                // Une autre requête a gagné la course et a déjà posé un nouveau
                // jeton en base avant nous. Ne rien réécrire, et surtout ne pas
                // réémettre de cookie ici : notre Set-Cookie contiendrait une
                // valeur dérivée de $oldToken, qui n'existe déjà plus en base.
                // S'il partait quand même vers le navigateur — par exemple
                // parce que la réponse de la requête gagnante arrive après la
                // nôtre — il écraserait le cookie valide de la gagnante par un
                // cookie que la base ne reconnaît plus, et l'utilisateur se
                // retrouverait déconnecté au prochain rafraîchissement alors
                // qu'aucun cookie n'a été volé. On laisse simplement passer la
                // requête avec l'utilisateur déjà résolu par la garde : elle
                // reste authentifiée normalement, sans toucher au cookie.
                return $next($request);
            }

            // On a gagné la course : aligner l'instance en mémoire sur ce que
            // l'UPDATE vient de poser en base, puis réémettre le cookie avec
            // le nouveau jeton. Sans cela l'ancien cookie resterait valable
            // jusqu'à son expiration.
            $user->setRememberToken($newToken);
            Auth::guard('web')->login($user, remember: true);
        }

        return $next($request);
    }
}
