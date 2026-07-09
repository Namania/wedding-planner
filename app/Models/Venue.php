<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
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
