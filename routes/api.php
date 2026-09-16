<?php

use App\Http\Controllers\AnimationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CatererController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FloristController;
use App\Http\Controllers\GalleryAdminController;
use App\Http\Controllers\GalleryAuthController;
use App\Http\Controllers\GalleryPhotoController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\OutfitController;
use App\Http\Controllers\SeatingTableController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimelineEventController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\WeddingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => config('app.name'),
        'status' => 'Up and running',
    ]);
});

Route::post('/login', [AuthController::class, 'login']);

Route::get('gallery/photos/{photo}/{variant}', [GalleryPhotoController::class, 'file'])
    ->middleware(['signed', 'throttle:gallery-files'])
    ->name('gallery.photos.file');

Route::get('gallery/invite/{token}', [GalleryAuthController::class, 'checkInvite'])
    ->middleware('throttle:gallery-invite');
Route::post('gallery/register', [GalleryAuthController::class, 'register'])
    ->middleware('throttle:gallery-register');
Route::post('gallery/login', [GalleryAuthController::class, 'login'])
    ->middleware('throttle:gallery-login');

Route::middleware(['auth:sanctum', 'gallery.guest'])->prefix('gallery')->group(function () {
    Route::get('me', [GalleryAuthController::class, 'me']);
    Route::post('logout', [GalleryAuthController::class, 'logout']);

    Route::get('photos', [GalleryPhotoController::class, 'index']);
    Route::post('photos', [GalleryPhotoController::class, 'store'])
        ->middleware('throttle:gallery-upload');
    Route::delete('photos/{photo}', [GalleryPhotoController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'admin.user'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('metrics', [DashboardController::class, 'metrics']);
    Route::apiResource('guests', GuestController::class);
    Route::apiResource('venues', VenueController::class);
    Route::apiResource('caterers', CatererController::class);
    Route::apiResource('florists', FloristController::class);
    Route::apiResource('animations', AnimationController::class);
    Route::apiResource('outfits', OutfitController::class);
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('seating-tables', SeatingTableController::class);
    Route::apiResource('timeline-events', TimelineEventController::class);
    Route::apiResource('simulations', SimulationController::class);
    Route::patch('simulations/{simulation}/activate', [SimulationController::class, 'activate']);

    Route::get('budget', [BudgetController::class, 'show']);
    Route::put('budget', [BudgetController::class, 'update']);

    Route::get('wedding', [WeddingController::class, 'show']);
    Route::put('wedding', [WeddingController::class, 'update']);

    Route::prefix('gallery-admin')->group(function () {
        Route::get('settings', [GalleryAdminController::class, 'showSettings']);
        Route::put('settings', [GalleryAdminController::class, 'updateSettings']);
        Route::post('settings/rotate-token', [GalleryAdminController::class, 'rotateToken']);

        Route::get('guests', [GalleryAdminController::class, 'guests']);
        Route::patch('guests/{guest}/ban', [GalleryAdminController::class, 'ban']);
        Route::patch('guests/{guest}/unban', [GalleryAdminController::class, 'unban']);
        Route::post('guests/{guest}/reset-pin', [GalleryAdminController::class, 'resetPin']);
        Route::delete('guests/{guest}', [GalleryAdminController::class, 'destroyGuest']);

        Route::get('photos', [GalleryAdminController::class, 'photos']);
        Route::patch('photos/{photo}/hide', [GalleryAdminController::class, 'hide']);
        Route::patch('photos/{photo}/unhide', [GalleryAdminController::class, 'unhide']);
        Route::delete('photos/{photo}', [GalleryAdminController::class, 'destroyPhoto']);

        Route::get('export', [GalleryAdminController::class, 'export']);
    });
});
