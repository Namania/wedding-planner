<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caterer extends Model
{
    // Types de prestation de repas pour un mariage, proposés par les traiteurs.
    public const SERVICE_TYPES = [
        'cocktail',
        'cocktail_dinatoire',
        'seated',
        'buffet',
        'food_truck',
        'brunch',
        'live_cooking',
    ];

    // Suivi de la demande de devis auprès du prestataire.
    public const QUOTE_STATUSES = [
        'requested',
        'accepted',
        'refused',
    ];

    protected $fillable = [
        'name',
        'price_per_person',
        'phone',
        'website',
        'service_type',
        'note',
        'quote_status',
    ];

    protected $casts = [
        'price_per_person' => 'decimal:2',
    ];
}
