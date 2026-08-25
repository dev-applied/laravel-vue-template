<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Exports\Http\Controllers\ExportController;

// Literal `sources` before the {export} wildcard, or it binds as an id.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
    Route::get('/exports/sources', [ExportController::class, 'sources'])->name('exports.sources');
    Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');
    Route::get('/exports/{export}', [ExportController::class, 'show'])->name('exports.show');
    Route::get('/exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
    Route::delete('/exports/{export}', [ExportController::class, 'destroy'])->name('exports.destroy');
});
