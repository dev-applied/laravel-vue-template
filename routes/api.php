<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('/', [AuthController::class, 'me']);
        Route::post('/', [AuthController::class, 'login'])->middleware('throttle:6,1');
        Route::delete('/', [AuthController::class, 'logout']);
        Route::post('/impersonate', [AuthController::class, 'impersonate'])->middleware('auth:sanctum');
        Route::delete('/stop-impersonating', [AuthController::class, 'stopImpersonating'])->middleware('auth:sanctum');
    });

    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/forgot-password', [ForgotPasswordController::class, 'send']);
        Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserController::class);

        Route::get('/files/{file}/{size?}', [FileController::class, 'url']);
        Route::get('/files/view/{file}', [FileController::class, 'view']);
        Route::get('/files/download/{file}/{size?}', [FileController::class, 'download']);
        Route::post('/files', [FileController::class, 'store']);
        Route::delete('/files/{file}', [FileController::class, 'destroy']);
    });

    Route::fallback(fn () => response()->json(['message' => 'Not Found'], 404));
});
