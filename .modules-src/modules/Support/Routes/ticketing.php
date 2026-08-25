<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\TicketReplyController;

// Loaded only when the ticketing variant is installed; mode=contact drops this
// file and ModuleServiceProvider skips it when absent.
// Gated for a sharper reason than the read routes: `replies.store` mails an
// arbitrary body to the ticket's email address, from our domain, into a thread
// the customer already trusts. Ungated, that is an authenticated phishing relay.
Route::middleware(['auth:sanctum', 'can:manage-support'])->group(function () {
    Route::post('/support/tickets/{ticket}/replies', [TicketReplyController::class, 'store'])
        ->name('support.tickets.replies.store');
});
