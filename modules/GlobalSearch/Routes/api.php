<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\GlobalSearch\Http\Controllers\SearchController;

Route::middleware('auth:sanctum')->group(function () {
    // The palette hits this on every debounced keystroke, so it is a GET and
    // shapes nothing: authorisation is per-source, inside the registry.
    Route::get('search', SearchController::class)->name('search');
    Route::get('search/types', [SearchController::class, 'types'])->name('search.types');
});
