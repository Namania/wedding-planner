<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wedding extends Model
{
    protected $fillable = [
        'spouse_1_name',
        'spouse_2_name',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
