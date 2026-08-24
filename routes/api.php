<?php

declare(strict_types=1);

use App\Http\Controllers\ItemController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Login / me / logout / impersonation / forgot-password live in
// modules/Auth/Routes/api.php, and the /files/* routes in
// modules/Files/Routes/api.php (both registered under this same v1 prefix).
Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('items', ItemController::class);
    });

    Route::fallback(fn () => response()->json(['message' => 'Not Found'], 404));
});
