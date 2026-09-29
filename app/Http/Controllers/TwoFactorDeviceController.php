<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorTrustedDevice;
use App\Services\TrustedDeviceRegistry;
use Illuminate\Http\Request;
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

    public function destroyAll(Request $request)
    {
        $this->devices->revokeAll($request->user());

        // Supprimer les lignes de la table des appareils ne suffit pas : une
        // session déjà ouverte depuis un appareil perdu survivrait à cette
        // révocation et se rouvrirait toute seule pendant la durée du cookie
        // « remember me ». Faire tourner remember_token invalide ce cookie
        // sur tous les appareils à la fois, ce qui coupe réellement les
        // reconnexions silencieuses — c'est le scénario de l'ordinateur perdu
        // qui justifie cet écran. La révocation d'un seul appareil (destroy)
        // ne porte volontairement que sur les connexions futures : elle ne
        // touche pas au jeton remember.
        $request->user()->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        return $this->respond('Tous les appareils ont été révoqués.', true);
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
