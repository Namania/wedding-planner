<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Simulation extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'venue_id',
        'caterer_id',
        'florist_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function caterer(): BelongsTo
    {
        return $this->belongsTo(Caterer::class);
    }

    public function florist(): BelongsTo
    {
        return $this->belongsTo(Florist::class);
    }
}
