<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->configureAdminRateLimiting();

        $this->configureGalleryRateLimiting();
    }

    private function configureAdminRateLimiting(): void
    {
        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute(5)->by('al|'.$request->ip()),
            Limit::perMinute(10)->by('ale|'.mb_strtolower((string) $request->input('email'))),
        ]);

        // Un code à 6 chiffres est devinable par force brute : on limite aussi
        // par jeton de challenge, pour qu'une même tentative de connexion ne
        // puisse pas être bombardée depuis plusieurs adresses.
        RateLimiter::for('admin-2fa', fn (Request $request) => [
            Limit::perMinute(5)->by('a2|'.$request->ip()),
            Limit::perMinute(10)->by('a2t|'.sha1((string) $request->input('challenge_token'))),
        ]);
    }

    private function configureGalleryRateLimiting(): void
    {
        RateLimiter::for('gallery-invite', fn (Request $request) => Limit::perMinute(20)->by('gi|'.$request->ip()));

        RateLimiter::for('gallery-register', fn (Request $request) => Limit::perHour(10)->by('gr|'.$request->ip()));

        RateLimiter::for('gallery-login', fn (Request $request) => [
            Limit::perMinute(5)->by('gl|'.$request->ip()),
            Limit::perMinute(10)->by('gln|'.mb_strtolower((string) $request->input('name'))),
        ]);

        RateLimiter::for('gallery-upload', fn (Request $request) => Limit::perMinutes(10, 30)->by('gu|'.($request->user()?->getKey() ?? $request->ip())));

        RateLimiter::for('gallery-files', fn (Request $request) => Limit::perMinute(300)->by('gf|'.$request->ip()));
    }
}
