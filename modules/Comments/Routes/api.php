<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Comments\Http\Controllers\CommentController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/comments/{type}/{id}', [CommentController::class, 'index'])->name('comments.index');
    Route::post('/comments/{type}/{id}', [CommentController::class, 'store'])->name('comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
});
