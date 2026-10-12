<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Une dépense réellement payée, par opposition au budget prévu par la
 * simulation active.
 */
class Expense extends Model
{
    use BroadcastsChanges, HasFactory;

    protected $fillable = [
        'amount',
        'note',
        'paid_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}
