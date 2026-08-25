<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Modules\Realtime\Http\Controllers\RealtimeConfigController;

// Public: none of it is secret, and the client needs it before it can
// authenticate anything.
Route::get('realtime/config', RealtimeConfigController::class)->name('realtime.config');

// The broadcasting auth endpoint, behind Sanctum rather than the `web` guard
// Laravel registers by default. An SPA on cookie auth works either way; a
// Capacitor build on a bearer token does not, and the failure is a socket that
// connects and then silently authorises no private channel at all.
//
// Registered by hand rather than with Broadcast::routes(), which would add its
// own prefix on top of the api/v1 this file is already grouped under.
Route::middleware('auth:sanctum')
    ->post('broadcasting/auth', function () {
        // Refuse outright when broadcasting is not configured.
        //
        // Laravel's `null` broadcaster — the default in a fresh install and in
        // the test environment — authorises EVERY channel. Nothing leaks while
        // the driver is null, because nothing is broadcast either; the problem
        // is that the endpoint rubber-stamps, so the day somebody sets a real
        // driver they inherit an auth path that has never once said no, and
        // whichever guard they forgot to write is now open.
        //
        // Measured in the running app before this existed: a signed-in user
        // POSTing `private-nobody-declared-this` got 200.
        abort_if(
            config('broadcasting.default') === 'null' || blank(config('realtime.client.key')),
            403,
            'Realtime is not configured on this environment.',
        );

        return Broadcast::auth(request());
    })
    ->name('realtime.auth');
