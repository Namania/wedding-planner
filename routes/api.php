<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CatererController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FloristController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\SeatingTableController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimelineEventController;
use App\Http\Controllers\VenueController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => config('app.name'),
        'status' => 'Up and running',
    ]);
});

Route::get('metrics', [DashboardController::class, 'metrics']);
Route::apiResource('guests', GuestController::class);
Route::apiResource('venues', VenueController::class);
Route::apiResource('caterers', CatererController::class);
Route::apiResource('florists', FloristController::class);
Route::apiResource('tasks', TaskController::class);
Route::apiResource('seating-tables', SeatingTableController::class);
Route::apiResource('timeline-events', TimelineEventController::class);
Route::apiResource('simulations', SimulationController::class);
Route::patch('simulations/{simulation}/activate', [SimulationController::class, 'activate']);

Route::get('budget', [BudgetController::class, 'show']);
Route::put('budget', [BudgetController::class, 'update']);
