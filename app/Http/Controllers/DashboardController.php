<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Guest;
use App\Models\Simulation;
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

        $activeSimulation = Simulation::where('is_active', true)->with(['venue', 'caterer', 'florist', 'animations', 'outfits'])->first();

        // Le traiteur est facturé au nombre d'invités qui viendront réellement :
        // on exclut ceux ayant décliné (confirmed = false), mais on garde ceux
        // en attente de réponse (confirmed = null), par prudence budgétaire.
        $attendingGuestsCount = Guest::where('confirmed', true)->orWhereNull('confirmed')->count();

        // Un devis refusé ne compte plus dans le budget utilisé (cf. BudgetView côté front).
        $simulationTotal = 0;
        if ($activeSimulation) {
            if ($activeSimulation->venue && $activeSimulation->venue->quote_status !== 'refused') {
                $simulationTotal += (float) $activeSimulation->venue->price;
            }
            if ($activeSimulation->florist && $activeSimulation->florist->quote_status !== 'refused') {
                $simulationTotal += (float) $activeSimulation->florist->price;
            }
            if ($activeSimulation->caterer && $activeSimulation->caterer->quote_status !== 'refused') {
                $simulationTotal += (float) $activeSimulation->caterer->price_per_person * $attendingGuestsCount;
            }
            $simulationTotal += (float) $activeSimulation->animations->where('quote_status', '!=', 'refused')->sum('price');
            $simulationTotal += (float) $activeSimulation->outfits->where('quote_status', '!=', 'refused')->sum('price');
        }

        $metrics['budget'] = [
            'current' => $simulationTotal,
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
