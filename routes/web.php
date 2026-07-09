<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any?}', function () {
    return response()->file(public_path('build/index.html'));
})->where('any', '^(?!api).*$');
