<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\UserController;

// Mounted at `manage/users`, NOT `users`.
//
// The kernel keeps a read-only `users` typeahead that every ownership picker
// hits, gated on auth alone. This surface is administration — it is gated on
// `can:manage-users` and returns far more per row. Sharing one path would mean
// either a regular user's autocomplete 403s, or the management list leaks to
// everyone; separate paths make the two audiences explicit.
Route::middleware(['auth:sanctum', 'can:manage-users'])->prefix('manage')->name('manage.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
