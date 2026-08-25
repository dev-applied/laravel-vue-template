<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\EntitlementSwitcherController;

// Registered only when the switcher variant is installed. The controller
// refuses regardless unless the env flag is on AND the environment is not
// production — the route existing is not the control.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/billing/qa/entitlement', EntitlementSwitcherController::class)->name('billing.qa.entitlement');
});
