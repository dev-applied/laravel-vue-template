<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Otp\Http\Controllers\OtpController;

// Public, and throttled twice over: Laravel's own limiter here, plus the
// per-identifier and per-IP limits inside OtpManager.
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/otp/request', [OtpController::class, 'request'])->name('otp.request');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify');
});
