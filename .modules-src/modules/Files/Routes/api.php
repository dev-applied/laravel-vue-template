<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Files\Http\Controllers\FileController;

// Literal-prefixed routes are registered BEFORE the greedy
// `/files/{file}/{size?}` — otherwise that pattern swallows `/files/view/5`
// as file="view", size="5" and the route 404s on model binding. The template
// this module was extracted from had exactly that bug.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/files/meta/{file}', [FileController::class, 'show'])->name('files.show');
    Route::get('/files/view/{file}', [FileController::class, 'view'])->name('files.view');
    Route::get('/files/download/{file}/{size?}', [FileController::class, 'download'])->name('files.download');
    Route::get('/files/{file}/{size?}', [FileController::class, 'url'])->name('files.url');

    Route::post('/files', [FileController::class, 'store'])->name('files.store');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
});
