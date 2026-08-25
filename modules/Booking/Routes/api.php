<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\AvailabilityController;
use Modules\Booking\Http\Controllers\BookingController;

// Public: booking a slot usually happens before anyone has an account.
// Throttled, because it is an open write endpoint.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/booking/{slug}/availability', AvailabilityController::class)->name('booking.availability');
    Route::post('/booking/{slug}', [BookingController::class, 'store'])->name('booking.store');

    // The reference is the secret, which is why it is random and not the id.
    Route::get('/booking/reference/{reference}', [BookingController::class, 'show'])->name('booking.show');
    Route::post('/booking/reference/{reference}/cancel', [BookingController::class, 'cancel'])->name('booking.cancel');
});

Route::middleware(['auth:sanctum', 'can:manage-bookings'])->group(function () {
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/bookings/{booking}/approve', [BookingController::class, 'approve'])->name('bookings.approve');
});
