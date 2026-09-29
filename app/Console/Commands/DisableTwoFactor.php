<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TrustedDeviceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DisableTwoFactor extends Command
{
    protected $signature = 'user:disable-2fa {email}';

    protected $description = "Désactive la double authentification d'un compte admin (téléphone perdu)";

    public function handle(TrustedDeviceRegistry $devices): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("Aucun compte avec l'adresse {$this->argument('email')}.");

            return self::FAILURE;
        }

        $revoked = $devices->revokeAll($user);

        // Effacer le seul secret ne suffirait pas : login() teste l'appareil de
        // confiance AVANT hasTwoFactorEnabled(), donc un navigateur qui porte
        // encore un cookie `trusted_device` valide ouvrirait directement une
        // session, sur un compte désormais sans second facteur — durablement et
        // sans le moindre signal. Cette commande étant le filet de sécurité du
        // projet (téléphone perdu, compte peut-être compromis), elle coupe tout :
        // le secret, les appareils de confiance, et le jeton « remember me » qui
        // rouvrirait seul les sessions pendant une semaine.
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        $this->info("Double authentification désactivée pour {$user->email}.");
        $this->line(sprintf(
            '%d appareil(s) de confiance révoqué(s) et jeton de reconnexion renouvelé : toutes les reconnexions silencieuses sont coupées.',
            $revoked,
        ));
        $this->line('Un nouvel enrôlement sera demandé à la prochaine connexion, depuis n\'importe quel appareil.');

        return self::SUCCESS;
    }
}
