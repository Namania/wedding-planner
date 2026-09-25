<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DisableTwoFactor extends Command
{
    protected $signature = 'user:disable-2fa {email}';

    protected $description = "Désactive la double authentification d'un compte admin (téléphone perdu)";

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("Aucun compte avec l'adresse {$this->argument('email')}.");

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->info("Double authentification désactivée pour {$user->email}.");
        $this->line('Un nouvel enrôlement sera demandé à la prochaine connexion.');

        return self::SUCCESS;
    }
}
