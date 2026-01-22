<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\UserController;
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
        Route::get('', [AuthController::class, 'me'])->name('auth.me');
        Route::post('', [AuthController::class, 'login'])->name('auth.login');
        Route::delete('', [AuthController::class, 'logout'])->name('auth.logout');
    });

    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('forgot-password.send');
    Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset'])->name('forgot-password.reset');
    Route::apiResource('users', UserController::class);

    Route::group(['middleware' => ['auth']], function () {
        Route::post('/auth/impersonate', [AuthController::class, 'impersonate'])->name('auth.impersonate');
        Route::delete('/auth/stop-impersonating', [AuthController::class, 'stopImpersonating'])->name('auth.stop-impersonating');

        // File Routes
        Route::group(['prefix' => '/files'], function () {
            Route::get('/{file}/{size?}', [FileController::class, 'url'])->whereNumber('file')->name('files.url');
            Route::get('/view/{file}', [FileController::class, 'show'])->whereNumber('file')->name('files.show');
            Route::get('/download/{file}/{size?}', [FileController::class, 'download'])->whereNumber('file')->name('files.download');
            Route::post('', [FileController::class, 'store'])->name('files.store');
            Route::delete('/{file}', [FileController::class, 'destroy'])->whereNumber('file')->name('files.destroy');
        });
    });
});

Route::fallback(function () {
    abort(404, 'Route Not Found: '.request()->path());
});
