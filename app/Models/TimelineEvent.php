<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimelineEvent extends Model
{
    protected $fillable = [
        'title',
        'starts_at',
        'note',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
    ];
}
