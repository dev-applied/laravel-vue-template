<?php

declare(strict_types=1);

use App\Http\Controllers\FileController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Login / me / logout / impersonation / forgot-password live in
// modules/Auth/Routes/api.php (registered under this same v1 prefix).
Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('items', ItemController::class);

        Route::get('/files/{file}/{size?}', [FileController::class, 'url']);
        Route::get('/files/view/{file}', [FileController::class, 'view']);
        Route::get('/files/download/{file}/{size?}', [FileController::class, 'download']);
        Route::post('/files', [FileController::class, 'store']);
        Route::delete('/files/{file}', [FileController::class, 'destroy']);
    });

    Route::fallback(fn () => response()->json(['message' => 'Not Found'], 404));
});
