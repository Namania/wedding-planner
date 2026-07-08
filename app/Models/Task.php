<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    public const CATEGORIES = [
        'administratif',
        'prestataires',
        'tenues',
        'deco',
        'invitations',
        'beaute',
        'logistique',
        'autre',
    ];

    public const STATUSES = [
        'todo',
        'in_progress',
        'done',
    ];

    protected $fillable = [
        'title',
        'category',
        'status',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];
}
