<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\EntitlementController;
use Modules\Billing\Http\Controllers\RevenueCatWebhookController;

// No auth middleware and no CSRF: RevenueCat authenticates with the shared
// secret in the Authorization header, checked in constant time inside the
// controller. An unset secret rejects everything.
Route::post('/billing/webhook/revenuecat', RevenueCatWebhookController::class)
    ->name('billing.webhook');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/billing/entitlement', [EntitlementController::class, 'show'])->name('billing.entitlement');
});
