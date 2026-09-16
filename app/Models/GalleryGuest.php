<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class GalleryGuest extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'name_normalized',
        'pin_hash',
        'created_ip',
        'last_seen_at',
        'banned_at',
    ];

    protected $hidden = [
        'pin_hash',
        'created_ip',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'banned_at' => 'datetime',
    ];

    public static function normalizeName(string $name): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GalleryPhoto::class);
    }
}
