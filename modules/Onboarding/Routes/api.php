<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Onboarding\Http\Controllers\OnboardingController;

// Never behind the `onboarded` middleware — these are the endpoints somebody
// uses to BECOME onboarded, and gating them locks the user out of the only
// screen that would release them.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding/skip', [OnboardingController::class, 'skipAll'])->name('onboarding.skip-all');
    Route::post('onboarding/{step}/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('onboarding/{step}/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');
});
