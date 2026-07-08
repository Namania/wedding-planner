<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Florist extends Model
{
    // Styles floraux proposés pour un mariage.
    public const STYLES = [
        'champetre',
        'romantique',
        'moderne',
        'exotique',
        'boheme',
        'classique',
        'luxueux',
    ];

    protected $fillable = [
        'name',
        'price',
        'phone',
        'website',
        'style',
        'note',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
