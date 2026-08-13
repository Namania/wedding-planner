<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Florist extends Model
{
    use BroadcastsChanges, HasFactory;

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

    // Suivi de la demande de devis auprès du prestataire.
    public const QUOTE_STATUSES = [
        'requested',
        'accepted',
        'refused',
    ];

    protected $fillable = [
        'name',
        'price',
        'phone',
        'website',
        'style',
        'note',
        'quote_status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
