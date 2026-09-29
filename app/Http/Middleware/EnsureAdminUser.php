<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Le test portait sur le type de l'objet authentifié. Depuis que les
        // invités sont eux aussi des User, seul le rôle distingue les deux.
        if (! $user instanceof User || ! $user->isAdmin()) {
            abort(403, 'Réservé aux administrateurs.');
        }

        return $next($request);
    }
}
