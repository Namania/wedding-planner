<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $fillable = [
        'name',
        'website',
        'maps_url',
        'price',
        'note',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
