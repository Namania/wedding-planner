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

    protected $fillable = [
        'name',
        'price_per_person',
        'phone',
        'website',
        'service_type',
        'note',
    ];

    protected $casts = [
        'price_per_person' => 'decimal:2',
    ];
}
