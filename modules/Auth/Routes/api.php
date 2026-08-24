<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\ForgotPasswordController;

// Registered by ModuleServiceProvider under api/v1 with the `api` middleware
// group. Paths are unchanged from the pre-module kernel so the frontend user
// store (/auth, /forgot-password) and Capacitor builds need no edits.
//
// When the OAuth layer is on, the login/logout routes additionally start a web
// session (StartSession + cookies) so a browser that logs into the SPA can
// clear Passport's /oauth/authorize consent without a second login. The session
// stack is prepended only then — a Sanctum-only project's auth stays stateless.
$sessionStack = config('auth.oauth.enabled', false) ? [
    Illuminate\Cookie\Middleware\EncryptCookies::class,
    Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    Illuminate\Session\Middleware\StartSession::class,
] : [];

Route::prefix('auth')->group(function () use ($sessionStack) {
    Route::get('/', [AuthController::class, 'me']);
    Route::post('/', [AuthController::class, 'login'])->middleware([...$sessionStack, 'throttle:6,1']);
    Route::delete('/', [AuthController::class, 'logout'])->middleware($sessionStack);
    Route::post('/impersonate', [AuthController::class, 'impersonate'])->middleware('auth:sanctum');
    Route::delete('/stop-impersonating', [AuthController::class, 'stopImpersonating'])->middleware('auth:sanctum');
});

Route::middleware('throttle:6,1')->group(function () {
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send']);
    Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);
});
