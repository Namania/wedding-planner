<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimelineEvent extends Model
{
    use BroadcastsChanges, HasFactory;

    protected $fillable = [
        'title',
        'starts_at',
        'note',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
    ];
}
