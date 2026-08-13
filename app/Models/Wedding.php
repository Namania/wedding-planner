<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Model;

class Wedding extends Model
{
    use BroadcastsChanges;

    protected $fillable = [
        'spouse_1_name',
        'spouse_2_name',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
