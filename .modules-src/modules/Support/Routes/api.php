<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\TicketController;

// Public submission — throttled, since anyone can post to it.
Route::middleware('throttle:5,1')->post('/support/tickets', [TicketController::class, 'store'])
    ->name('support.tickets.store');

// `can:manage-support` is load-bearing. Without it any authenticated user reads
// every ticket in the queue — and customers put passwords and account details in
// a support form as a matter of routine.
Route::middleware(['auth:sanctum', 'can:manage-support'])->group(function () {
    Route::get('/support/tickets', [TicketController::class, 'index'])->name('support.tickets.index');
    Route::get('/support/tickets/{ticket}', [TicketController::class, 'show'])->name('support.tickets.show');
    Route::put('/support/tickets/{ticket}', [TicketController::class, 'update'])->name('support.tickets.update');
    Route::delete('/support/tickets/{ticket}', [TicketController::class, 'destroy'])->name('support.tickets.destroy');
});
