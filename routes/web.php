<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$spa = function () {
    $index = (string) config('app.spa_index');
    abort_unless(is_file($index), 503, 'The web app has not been built yet: run `npm run build` in frontend/.');

    // Never cached: a deploy must reach every browser on its next load; the
    // hashed assets under /spa/assets are what the browser caches.
    return response((string) file_get_contents($index), 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-cache, no-store, must-revalidate']);
};

Route::get('/', $spa);

// Session-based login for the first-party SPA (Sanctum stateful auth),
// namespaced under /auth so it never collides with the SPA's own client-side
// routes (e.g. the React "/login" page). External/third-party API clients
// authenticate with a Bearer token instead and never touch these routes.
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/mfa/verify', [AuthController::class, 'mfaVerify'])->middleware('throttle:10,1');
Route::post('/auth/mfa/setup', [AuthController::class, 'mfaSetup'])->middleware('auth:sanctum');
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Client-side routes (/dashboard, /sell/pos …) are the SPA's; unknown API,
// auth and Sanctum paths stay real 404s so clients never receive HTML.
Route::fallback(function (Request $request) use ($spa) {
    abort_if($request->is('api/*', 'auth/*', 'sanctum/*'), 404);

    return $spa();
});
