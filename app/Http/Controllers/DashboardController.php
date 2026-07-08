<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Guest;
use App\Models\Task;
use App\Models\TimelineEvent;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: '/api/metrics',
        summary: 'Metrics',
        tags: ['Dashboard'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Metrics pour le dashboard',
            ),
        ]
    )]
    public function metrics()
    {
        $metrics = [];

        $metrics['guests'] = [
            'confirmed' => Guest::whereConfirmed(true)->count(),
            'total' => Guest::count(),
        ];

        $metrics['budget'] = [
            // TODO: à calculer depuis les vraies dépenses une fois le backend Expense en place.
            'current' => 12450,
            'max' => (float) Budget::firstOrCreate()->total,
        ];

        $metrics['tasks'] = [
            'done' => Task::where('status', 'done')->count(),
            'total' => Task::count(),
            'overdue' => Task::where('status', '!=', 'done')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->startOfDay())
                ->count(),
        ];

        $nextEvent = TimelineEvent::where('starts_at', '>=', now())->orderBy('starts_at')->first();

        $metrics['next_event'] = $nextEvent ? [
            'title' => $nextEvent->title,
            'starts_at' => $nextEvent->starts_at->toIso8601String(),
        ] : null;

        return response()->json($metrics);
    }
}
