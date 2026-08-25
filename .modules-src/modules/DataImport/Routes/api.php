<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\DataImport\Http\Controllers\ImportController;

// Literal `targets` before the {import} wildcard.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('/imports/targets', [ImportController::class, 'targets'])->name('imports.targets');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('/imports/{import}/dry-run', [ImportController::class, 'dryRun'])->name('imports.dry-run');
    Route::post('/imports/{import}/run', [ImportController::class, 'run'])->name('imports.run');
    Route::delete('/imports/{import}', [ImportController::class, 'destroy'])->name('imports.destroy');
});
