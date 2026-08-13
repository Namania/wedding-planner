<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest extends Model
{
    use BroadcastsChanges, HasFactory;

    // Moments du mariage auxquels un invité peut assister.
    public const ATTENDANCE_MOMENTS = [
        'ceremony',
        'cocktail',
        'dinner',
        'brunch',
    ];

    protected $fillable = [
        'name',
        'role',
        'confirmed',
        'attendance',
        'seating_table_id',
    ];

    protected $casts = [
        'confirmed' => 'boolean',
        'attendance' => 'array',
    ];

    // Un invité assiste par défaut à la cérémonie et au vin d'honneur.
    protected $attributes = [
        'attendance' => '["ceremony","cocktail"]',
    ];

    public function seatingTable(): BelongsTo
    {
        return $this->belongsTo(SeatingTable::class);
    }
}
