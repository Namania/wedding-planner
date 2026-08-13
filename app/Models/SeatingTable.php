<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingTable extends Model
{
    use BroadcastsChanges, HasFactory;

    protected $fillable = [
        'name',
        'capacity',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }
}
