<?php

namespace App\Services;

use App\Models\TwoFactorTrustedDevice;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Appareils dispensés du second facteur.
 *
 * Écrit contre le contrat Authenticatable et non contre User : les comptes
 * invités, quand ils existeront, doivent pouvoir s'en servir sans dupliquer
 * quoi que ce soit.
 */
class TrustedDeviceRegistry
{
    public const COOKIE = 'trusted_device';

    /**
     * Enregistre l'appareil et retourne le cookie à attacher à la réponse.
     * Le jeton en clair n'existe que le temps de cet aller-retour.
     */
    public function issueFor(Authenticatable $user, Request $request): Cookie
    {
        $token = Str::random(60);
        $days = (int) config('auth.trusted_device_days');

        TwoFactorTrustedDevice::create([
            'user_id' => $user->getAuthIdentifier(),
            'token_hash' => $this->hash($token),
            'name' => $this->label($request->userAgent()),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        return cookie(self::COOKIE, $token, $days * 24 * 60);
    }

    public function findValidFor(Authenticatable $user, ?string $token): ?TwoFactorTrustedDevice
    {
        if ($token === null || $token === '') {
            return null;
        }

        return TwoFactorTrustedDevice::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('token_hash', $this->hash($token))
            ->where('expires_at', '>', now())
            ->first();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Libellé court pour l'écran de révocation. On reste sur une heuristique
     * plutôt qu'une dépendance d'analyse de user-agent : il s'agit d'aider à
     * reconnaître un appareil, pas de statistiques.
     */
    private function label(?string $agent): string
    {
        if ($agent === null || $agent === '') {
            return 'Appareil inconnu';
        }

        // L'ordre compte : Edge annonce « Chrome » et « Safari » dans son
        // user-agent, et Chrome annonce « Safari ».
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        $platform = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return match (true) {
            $browser !== null && $platform !== null => $browser.' sur '.$platform,
            $browser !== null => $browser,
            $platform !== null => $platform,
            default => Str::limit($agent, 60),
        };
    }
}
