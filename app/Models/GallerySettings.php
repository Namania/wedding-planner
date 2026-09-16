<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GallerySettings extends Model
{
    protected $fillable = [
        'invite_token',
        'registrations_open',
        'registration_closes_at',
        'max_guests',
        'max_photos_per_guest',
    ];

    protected $casts = [
        'invite_token' => 'encrypted',
        'registrations_open' => 'boolean',
        'registration_closes_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::firstOrFail();
    }

    public function registrationsAllowed(): bool
    {
        if (! $this->registrations_open) {
            return false;
        }

        if ($this->registration_closes_at !== null && $this->registration_closes_at->isPast()) {
            return false;
        }

        return GalleryGuest::count() < $this->max_guests;
    }

    public function rotateInviteToken(): void
    {
        $this->update(['invite_token' => Str::random(64)]);
    }
}
