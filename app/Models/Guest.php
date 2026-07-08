<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $fillable = [
        'name',
        'role',
        'confirmed',
    ];

    protected $casts = [
        'confirmed' => 'boolean',
    ];
}
