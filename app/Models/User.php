<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
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
            'banned_at' => 'datetime',
            'last_seen_at' => 'datetime',
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

    public const ROLE_ADMIN = 'admin';

    public const ROLE_GUEST = 'guest';

    /**
     * Forme canonique d'une adresse, celle qui est stockée et celle sur
     * laquelle on cherche.
     *
     * Postgres compare les chaînes en respectant la casse. Sans cette
     * normalisation, un invité qui s'inscrit depuis son téléphone avec
     * `Camille@Exemple.com` — l'autocapitalisation mobile est la norme — et
     * retape `camille@exemple.com` plus tard reçoit « Adresse ou mot de passe
     * incorrect », indiscernable d'un mauvais mot de passe ; sans envoi de
     * mail, son seul recours est d'aller déranger les mariés. Elle est aussi
     * ce qui fait tenir l'unicité de l'email à travers les deux rôles, voulue
     * par la spec : sinon `Maries@exemple.com` en invité et
     * `maries@exemple.com` en administrateur coexisteraient.
     *
     * Une valeur qui n'est pas une chaîne devient une chaîne vide plutôt que
     * de faire échouer l'appel : la validation qui suit la rejettera, et les
     * clés de limitation de débit indexées là-dessus restent calculables.
     */
    public static function normalizeEmail(mixed $email): string
    {
        return is_string($email) ? mb_strtolower(trim($email)) : '';
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isGuest(): bool
    {
        return $this->role === self::ROLE_GUEST;
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GalleryPhoto::class);
    }

    /**
     * Le second facteur est-il imposé à l'enrôlement de ce compte ?
     *
     * Ne gouverne que l'enrôlement, jamais le challenge : un compte qui a déjà
     * un secret confirmé passera par le TOTP quoi qu'il arrive. Les invités de
     * la galerie en sont dispensés.
     *
     * Pour désactiver le second facteur d'un administrateur, passer par
     * `php artisan user:disable-2fa`.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->role !== self::ROLE_GUEST;
    }
}
