<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Tags\Http\Controllers\TagController;
use Modules\Tags\Http\Controllers\TaggableController;

Route::middleware('auth:sanctum')->group(function () {
    // The pool — what the autocomplete offers and what a filter lists.
    Route::get('/tags', [TagController::class, 'index'])->name('tags.index');

    // Tags on one record. Gated per-record by the registered ability.
    Route::get('/tags/{type}/{id}', [TaggableController::class, 'index'])->name('tags.record');
    Route::put('/tags/{type}/{id}', [TaggableController::class, 'sync'])->name('tags.sync');

    // Curating the pool itself is a separate, narrower permission: renaming or
    // merging a tag changes what everyone sees.
    Route::middleware('can:manage-tags')->group(function () {
        Route::put('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
        Route::post('/tags/{tag}/merge', [TagController::class, 'merge'])->name('tags.merge');
    });
});
