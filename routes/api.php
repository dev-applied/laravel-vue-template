<?php

declare(strict_types=1);

use App\Http\Controllers\ItemController;
use App\Http\Controllers\UserLookupController;
use Illuminate\Support\Facades\Route;

// Login / me / logout / impersonation / forgot-password live in
// modules/Auth/Routes/api.php, the /files/* routes in
// modules/Files/Routes/api.php, and user *management* in
// modules/Users/Routes/api.php (all registered under this same v1 prefix).
//
// `users` here is the read-only typeahead every AppAutoComplete uses to answer
// "who can I assign this to". It stays in the kernel because it is gated on
// auth alone — the Users module's management surface is a different path
// behind `can:manage-users`, so picking an owner never needs that permission.
Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('items', ItemController::class);
        Route::get('users', [UserLookupController::class, 'index'])->name('users.lookup');
    });

    Route::fallback(fn () => response()->json(['message' => 'Not Found'], 404));
});
