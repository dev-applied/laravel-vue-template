<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Favorites\Http\Controllers\FavoriteController;

// Everything here is the CURRENT user's own favourites, so auth is the only
// gate there is — there is no per-record permission to check, because a
// favourite says nothing about the record and grants nothing.
Route::middleware('auth:sanctum')->prefix('favorites')->name('favorites.')->group(function () {
    Route::get('/', [FavoriteController::class, 'index'])->name('index');
    Route::post('/{type}/{id}', [FavoriteController::class, 'toggle'])->name('toggle');
    Route::delete('/{type}/{id}', [FavoriteController::class, 'destroy'])->name('destroy');
});
