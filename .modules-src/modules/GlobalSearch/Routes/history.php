<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\GlobalSearch\Http\Controllers\SearchHistoryController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('search/history', [SearchHistoryController::class, 'index'])->name('search.history.index');
    Route::post('search/history', [SearchHistoryController::class, 'store'])->name('search.history.store');
    Route::delete('search/history', [SearchHistoryController::class, 'destroy'])->name('search.history.clear');
    Route::delete('search/history/{history}', [SearchHistoryController::class, 'destroy'])->name('search.history.destroy');
});
