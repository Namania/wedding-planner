<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    // Contrairement au lieu/traiteur/fleuriste (un seul choix par simulation),
    // plusieurs animations peuvent être retenues en même temps (DJ + feu d'artifice...).
    public function animations(): BelongsToMany
    {
        return $this->belongsToMany(Animation::class);
    }

    // Idem pour les tenues : plusieurs peuvent être retenues en même temps
    // (une par marié, voire plusieurs par marié).
    public function outfits(): BelongsToMany
    {
        return $this->belongsToMany(Outfit::class);
    }
}
