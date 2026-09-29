<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sur une requête porteuse d'un jeton Bearer, empêche la session de première
 * partie de l'emporter.
 *
 * Depuis la tâche 3, les invités de la galerie s'authentifient par session
 * comme les administrateurs : un jeton Bearer sur une route galerie n'est
 * plus qu'un résidu (un vieux client, ou l'ancien flux GalleryGuest tant que
 * la tâche 5 ne l'a pas retiré). Ce filet reste utile pour ce cas : Sanctum
 * essaie d'abord les gardes de `config('sanctum.guard')` — ici `['web']` — et
 * ne regarde le jeton Bearer que si aucune n'a répondu. Sur un navigateur qui
 * porte un cookie de session ou de reconnexion admin, toute requête vers la
 * galerie ressusciterait donc la session admin : `$request->user()` serait un
 * User administrateur, et EnsureGalleryGuest répondrait 403. Les mariés sont
 * les admins et ouvriront leur propre galerie ; avec la session longue, cette
 * fenêtre dure une semaine et se rouvre toute seule.
 *
 * Vider `sanctum.guard` le temps de la requête est le plus petit levier fiable :
 * c'est une option publique de Sanctum, relue à chaque résolution de la garde,
 * et la modification ne vit que dans le conteneur de CETTE requête (pas
 * d'Octane ici, donc aucune fuite vers la suivante). On n'y touche que si un
 * jeton est effectivement présent : sans en-tête Authorization, les routes qui
 * s'appuient sur la session continuent de fonctionner à l'identique.
 */
class PreferAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            config(['sanctum.guard' => []]);
        }

        return $next($request);
    }
}
