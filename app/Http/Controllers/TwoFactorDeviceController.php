<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorTrustedDevice;
use App\Services\TrustedDeviceRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TwoFactorDeviceController extends Controller
{
    public function __construct(private readonly TrustedDeviceRegistry $devices) {}

    public function index(Request $request)
    {
        $current = $this->devices->findValidFor(
            $request->user(),
            $request->cookie(TrustedDeviceRegistry::COOKIE),
        );

        return $this->devices->listFor($request->user())
            ->map(fn (TwoFactorTrustedDevice $device) => [
                'id' => $device->getKey(),
                'name' => $device->name,
                'ip_address' => $device->ip_address,
                'last_used_at' => $device->last_used_at,
                'expires_at' => $device->expires_at,
                'is_current' => $current !== null && $current->is($device),
            ]);
    }

    public function destroy(Request $request, TwoFactorTrustedDevice $device)
    {
        // L'appartenance se décide dans le service ; ici on choisit seulement
        // la réponse. 404 et non 403 : on ne confirme pas l'existence d'un
        // appareil qui appartient à quelqu'un d'autre.
        $wasCurrent = $this->isCurrent($request, $device);

        abort_unless($this->devices->revoke($request->user(), $device), 404);

        return $this->respond('Appareil révoqué.', $wasCurrent);
    }

    /**
     * « Déconnecter les autres appareils », au sens standard du terme : on
     * révoque les appareils de confiance, on ferme les sessions ouvertes
     * ailleurs, on invalide les cookies « remember me » des autres appareils —
     * et on laisse celui d'où part la demande en état de marche.
     */
    public function destroyAll(Request $request)
    {
        $user = $request->user();

        $this->devices->revokeAll($user);

        // Supprimer les lignes de la table des appareils ne suffit pas : une
        // session déjà ouverte depuis l'appareil perdu survivrait à cette
        // révocation, et son cookie « remember me » la rouvrirait tout seul
        // pendant une semaine. On coupe donc les deux : les sessions des
        // autres appareils d'abord, puis le jeton remember, qui invalide d'un
        // coup tous les cookies de reconnexion existants.
        $this->purgeOtherSessions($request, $user);

        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        // La rotation ci-dessus a tué NOTRE cookie de reconnexion aussi : sans
        // cette réémission, la personne qui vient de perdre son téléphone se
        // retrouverait déconnectée cinq minutes plus tard, sans explication.
        // On lui repose un cookie portant le nouveau jeton. Ça n'affaiblit
        // rien : les cookies des autres appareils sont déjà morts, et ce
        // nouveau cookie ne vaut que pour le navigateur qui reçoit la réponse.
        // login() fait au passage migrer la session courante, qui reste donc
        // valide sous un nouvel identifiant.
        Auth::guard('web')->login($user, remember: true);

        return $this->respond('Tous les autres appareils ont été déconnectés.', true);
    }

    /**
     * Ferme les sessions du compte, sauf celle de la requête en cours.
     *
     * Le driver `database` est le seul qui sache relier une session à son
     * compte (colonne `user_id`) : c'est celui de la production. Sous un autre
     * driver — `array` en test, `file` en local — il n'y a rien à balayer, et
     * la rotation du jeton remember ci-dessus reste le vrai coupe-circuit.
     */
    private function purgeOtherSessions(Request $request, Authenticatable $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    private function isCurrent(Request $request, TwoFactorTrustedDevice $device): bool
    {
        $current = $this->devices->findValidFor(
            $request->user(),
            $request->cookie(TrustedDeviceRegistry::COOKIE),
        );

        return $current !== null && $current->is($device);
    }

    /**
     * Effacer le cookie est indispensable quand l'appareil révoqué est celui
     * qu'on utilise : sans cela le navigateur continuerait de l'envoyer jusqu'à
     * son expiration, en se croyant de confiance alors que la base ne le
     * connaît plus.
     */
    private function respond(string $message, bool $clearCookie)
    {
        $response = response()->json(['message' => $message]);

        return $clearCookie
            ? $response->withoutCookie(TrustedDeviceRegistry::COOKIE)
            : $response;
    }
}
