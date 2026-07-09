<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Animation extends Model
{
    // Types d'animations proposés pour un mariage.
    public const TYPES = [
        'dj',
        'live_band',
        'photobooth',
        'fireworks',
        'magician',
        'show',
        'casino',
        'video_mapping',
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
        'type',
        'note',
        'quote_status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
