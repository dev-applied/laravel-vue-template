<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Otp\Http\Controllers\StepUpController;

Route::middleware(['auth:sanctum', 'throttle:20,1'])->group(function () {
    Route::post('/otp/step-up/request', [StepUpController::class, 'request'])->name('otp.step-up.request');
    Route::post('/otp/step-up/verify', [StepUpController::class, 'verify'])->name('otp.step-up.verify');
});
