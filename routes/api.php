<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ForgotPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group(['prefix' => '/v1'], function () {
    Route::group(['prefix' => '/auth'], function () {
        Route::get('', [AuthController::class, 'me']);
        Route::post('', [AuthController::class, 'login']);
        Route::delete('', [AuthController::class, 'logout']);
    });

    Route::post('/forgot-password', [ForgotPasswordController::class, 'send']);
    Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);
    Route::apiResource('users', UserController::class);

    Route::group(['middleware' => ['auth']], function () {
        Route::post('/auth/impersonate', [AuthController::class, 'impersonate']);
        Route::delete('/auth/stop-impersonating', [AuthController::class, 'stopImpersonating']);

        // File Routes
        Route::group(['prefix' => '/files'], function () {
            Route::get('/{file}/{size?}', [FileController::class, 'url'])->whereNumber('file');
            Route::get('/view/{file}', [FileController::class, 'show']);
            Route::get('/download/{file}/{size?}', [FileController::class, 'download']);
            Route::post('', [FileController::class, 'store']);
            Route::delete('/{file}', [FileController::class, 'destroy']);
        });
    });
});


Route::fallback(function () {
    abort(404, 'Route Not Found: ' . request()->path());
});
