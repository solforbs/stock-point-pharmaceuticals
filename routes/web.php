<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Session-based login for the first-party SPA (Sanctum stateful auth),
// namespaced under /auth so it never collides with the SPA's own client-side
// routes (e.g. the React "/login" page). External/third-party API clients
// authenticate with a Bearer token instead and never touch these routes.
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/mfa/verify', [AuthController::class, 'mfaVerify'])->middleware('throttle:10,1');
Route::post('/auth/mfa/setup', [AuthController::class, 'mfaSetup'])->middleware('auth:sanctum');
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
