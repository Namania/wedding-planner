<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Outfit extends Model
{
    use BroadcastsChanges, HasFactory;

    // À quel marié appartient une tenue candidate.
    public const SPOUSES = [
        'spouse_1',
        'spouse_2',
    ];

    // Suivi de la demande de devis auprès du prestataire.
    public const QUOTE_STATUSES = [
        'requested',
        'accepted',
        'refused',
    ];

    protected $fillable = [
        'name',
        'price',
        'spouse',
        'phone',
        'website',
        'image_path',
        'note',
        'quote_status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function simulations(): BelongsToMany
    {
        return $this->belongsToMany(Simulation::class);
    }
}
