<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Un secret seul ne suffit pas : tant qu'il n'a pas été confirmé par un
     * premier code valide, l'enrôlement est considéré comme non terminé.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Ce compte doit-il s'enrôler au second facteur s'il ne l'est pas encore ?
     *
     * Ne gouverne QUE l'enrôlement, jamais le challenge : un compte déjà
     * enrôlé passe par hasTwoFactorEnabled() sans jamais consulter cette
     * méthode, et un appareil de confiance court-circuite les deux. Autrement
     * dit, elle ne garantit à elle seule la présence d'aucun second facteur à
     * la connexion — c'est à ne pas oublier là où l'on croit couper la 2FA
     * d'un compte (voir user:disable-2fa, qui doit aussi purger les appareils
     * de confiance).
     *
     * Renvoie une constante pour l'instant : tous les comptes existants sont
     * des comptes d'administration. Quand le rôle invité arrivera, ce sera
     * $this->role !== 'guest', et c'est le seul endroit à reprendre — sans
     * cela, login() pousserait les invités vers l'enrôlement TOTP.
     */
    public function requiresTwoFactor(): bool
    {
        return true;
    }
}
