<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\ForgotPasswordController;
use Modules\Auth\Http\Controllers\SamlController;
use Modules\Auth\Http\Controllers\SsoController;

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
    // `can:impersonate-users` is load-bearing, not decoration: without it this
    // route hands any authenticated caller a bearer token for any user id.
    Route::post('/impersonate', [AuthController::class, 'impersonate'])
        ->middleware(['auth:sanctum', 'can:impersonate-users']);
    Route::delete('/stop-impersonating', [AuthController::class, 'stopImpersonating'])->middleware('auth:sanctum');
});

Route::middleware('throttle:6,1')->group(function () {
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send']);
    Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);
});

// ---------------------------------------------------------------------------
// Single sign-on. Two protocols, one finish line.
//
// Both OIDC and SAML end by minting a single-use handoff code and redirecting
// the browser to the app, which redeems it here. `exchange` is therefore gated
// on EITHER protocol being on, not on Socialite: a SAML-only project has no
// Socialite installed and would otherwise have no way to finish signing in.
// ---------------------------------------------------------------------------
$oidcEnabled = config('auth.sso.enabled', false) && class_exists(Laravel\Socialite\Facades\Socialite::class);
$samlEnabled = config('auth.saml.enabled', false) && class_exists(OneLogin\Saml2\Auth::class);

if ($oidcEnabled || $samlEnabled) {
    Route::post('auth/sso/exchange', [SsoController::class, 'exchange'])->middleware('throttle:10,1');

    // One discovery call for both protocols, so the login screen has a single
    // list to render and does not need to know which of them a project
    // installed. Read on every login-page load, hence the looser bucket.
    Route::get('auth/sso/providers', [SsoController::class, 'providers'])->middleware('throttle:60,1');
}

// OIDC via Socialite. Throttled per endpoint rather than as one bucket:
// `providers` is read on every login-page load and must not spend the same
// budget as the endpoints worth grinding.
if ($oidcEnabled) {
    Route::prefix('auth/sso')->group(function () {
        Route::middleware('throttle:10,1')->group(function () {
            Route::get('/{provider}/start', [SsoController::class, 'start']);
            Route::get('/{provider}/callback', [SsoController::class, 'callback']);
        });
    });
}

// SAML 2.0. `metadata` is public and deliberately looser: an IdP administrator
// needs it before anything else can be configured, and it discloses only what
// we would hand them by email anyway. `acs` is a cross-site form POST from the
// IdP with no CSRF token — fine, because api routes carry no session cookie, so
// there is no ambient authority to abuse; the assertion's signature is the
// authentication.
if ($samlEnabled) {
    Route::prefix('auth/saml')->group(function () {
        Route::get('/metadata', [SamlController::class, 'metadata'])->middleware('throttle:30,1');

        Route::middleware('throttle:10,1')->group(function () {
            Route::get('/start', [SamlController::class, 'start']);
            Route::post('/acs', [SamlController::class, 'acs']);
        });

        // Single Logout. Unauthenticated for the same reason `acs` is: the
        // message arrives as a top-level browser navigation from the IdP with
        // no cookie of ours on it, and its signature is the authentication.
        // Both verbs because IdPs are not consistent — Redirect binding is the
        // norm for SLO and the toolkit only reads the query string, but a few
        // POST the same parameters, and a 405 there looks like an outage.
        Route::match(['get', 'post'], '/sls', [SamlController::class, 'sls'])->middleware('throttle:20,1');

        // The SP-initiated half, and the only SAML route that needs a session:
        // it ends a specific person's, so it has to know whose.
        Route::post('/logout', [SamlController::class, 'logout'])
            ->middleware(['auth:sanctum', 'throttle:10,1']);
    });
}
