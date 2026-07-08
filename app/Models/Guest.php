<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest extends Model
{
    protected $fillable = [
        'name',
        'role',
        'confirmed',
        'seating_table_id',
    ];

    protected $casts = [
        'confirmed' => 'boolean',
    ];

    public function seatingTable(): BelongsTo
    {
        return $this->belongsTo(SeatingTable::class);
    }
}
