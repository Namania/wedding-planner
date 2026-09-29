<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGalleryGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        $guest = $request->user();

        if (! $guest instanceof User || ! $guest->isGuest()) {
            abort(403, 'Réservé aux invités de la galerie.');
        }

        if ($guest->isBanned()) {
            abort(403, 'Cet accès a été désactivé.');
        }

        if ($guest->last_seen_at === null || $guest->last_seen_at->lt(now()->subHour())) {
            $guest->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
