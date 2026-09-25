<?php

namespace App\Services;

use App\Models\User;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;

class TwoFactorAuthenticator
{
    /**
     * Tolérance de part et d'autre du pas de temps courant, pour absorber le
     * décalage d'horloge entre le téléphone et le serveur. 1 = ±30 secondes.
     */
    private const WINDOW = 1;

    /**
     * 160 bits, soit 32 caractères en base32 : la longueur recommandée par la
     * RFC 4226 et celle attendue par les applications d'authentification. Le
     * secret par défaut d'otphp est bien plus long et certaines applis le
     * refusent.
     */
    public function generateSecret(): string
    {
        return Base32::encodeUpperUnpadded(random_bytes(20));
    }

    /**
     * URI otpauth:// à encoder en QR code côté client.
     */
    public function provisioningUri(User $user, string $secret): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($user->email);
        $totp->setIssuer((string) config('app.name'));

        return $totp->getProvisioningUri();
    }

    public function verify(string $secret, string $code): bool
    {
        // Les applications d'authentification affichent le code par blocs :
        // on tolère les espaces d'un copier-coller.
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return TOTP::createFromSecret($secret)->verify($code, null, self::WINDOW);
    }
}
