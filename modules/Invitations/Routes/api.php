<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Invitations\Http\Controllers\AcceptInvitationController;
use Modules\Invitations\Http\Controllers\InvitationController;

// Public: the invitee has no account yet, by definition. Throttled because
// these endpoints take a guessable token.
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/invitations/accept', [AcceptInvitationController::class, 'show'])->name('invitations.preview');
    Route::post('/invitations/accept', [AcceptInvitationController::class, 'store'])->name('invitations.accept');
});

// `can:manage-invitations` is load-bearing: without it any authenticated user
// can invite an address they control, name any role, and accept it themselves.
Route::middleware(['auth:sanctum', 'can:manage-invitations'])->group(function () {
    Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
});
