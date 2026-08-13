<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use BroadcastsChanges;

    protected $fillable = [
        'total',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];
}
