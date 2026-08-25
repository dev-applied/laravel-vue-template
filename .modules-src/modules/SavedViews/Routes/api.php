<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\SavedViews\Http\Controllers\SavedViewController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/saved-views', [SavedViewController::class, 'index'])->name('saved-views.index');
    Route::post('/saved-views', [SavedViewController::class, 'store'])->name('saved-views.store');
    Route::put('/saved-views/{savedView}', [SavedViewController::class, 'update'])->name('saved-views.update');
    Route::delete('/saved-views/{savedView}', [SavedViewController::class, 'destroy'])->name('saved-views.destroy');
});
