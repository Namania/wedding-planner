<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use BroadcastsChanges, HasFactory;

    // Suivi de la demande de devis auprès du prestataire.
    public const QUOTE_STATUSES = [
        'requested',
        'accepted',
        'refused',
    ];

    protected $fillable = [
        'name',
        'website',
        'maps_url',
        'price',
        'note',
        'quote_status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
