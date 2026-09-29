<?php

namespace App\Services;

use App\Models\TwoFactorTrustedDevice;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * @param  mixed  $token  Typé large à dessein : hors requête stateful pour
     *                        Sanctum, EncryptCookies n'est pas dans le pipeline
     *                        et un en-tête `Cookie: trusted_device[x]=y` serait
     *                        parsé par PHP en tableau plutôt qu'en chaîne.
     */
    public function findValidFor(Authenticatable $user, mixed $token): ?TwoFactorTrustedDevice
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return $this->query($user)
            ->where('token_hash', $this->hash($token))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Les appareils encore valides, le plus récemment utilisé en tête.
     *
     * @return Collection<int, TwoFactorTrustedDevice>
     */
    public function listFor(Authenticatable $user): Collection
    {
        return $this->query($user)
            ->where('expires_at', '>', now())
            ->orderByDesc('last_used_at')
            ->get();
    }

    /**
     * Révoque un appareil précis. Renvoie false si l'appareil n'appartient pas
     * à ce compte : l'appelant décide quoi en faire — le contrôleur répond 404
     * pour ne pas confirmer l'existence d'un appareil qui est à quelqu'un
     * d'autre.
     */
    public function revoke(Authenticatable $user, TwoFactorTrustedDevice $device): bool
    {
        if ($device->user_id !== $user->getAuthIdentifier()) {
            return false;
        }

        $device->delete();

        return true;
    }

    /**
     * Révoque tous les appareils du compte et renvoie leur nombre.
     *
     * Ne touche volontairement ni au jeton « remember me » ni aux sessions :
     * ce service ne connaît que les appareils de confiance. Couper les
     * reconnexions silencieuses relève de l'appelant, qui seul sait quelle
     * session il doit éventuellement préserver.
     */
    public function revokeAll(Authenticatable $user): int
    {
        return $this->query($user)->delete();
    }

    /**
     * @return Builder<TwoFactorTrustedDevice>
     */
    private function query(Authenticatable $user): Builder
    {
        return TwoFactorTrustedDevice::query()->where('user_id', $user->getAuthIdentifier());
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
