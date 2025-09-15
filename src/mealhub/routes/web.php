<?php

use Illuminate\Support\Facades\Route;

// Minimal landing/health endpoint for browsers and uptime checks
Route::get('/', function () {
    return response()->json([
        'ok'       => true,
        'name'     => config('app.name'),
        'env'      => config('app.env'),
        'version'  => app()->version(),
        'time'     => now()->toIso8601String(),
        'docs'     => 'Use /api/* endpoints',
    ], 200);
});
