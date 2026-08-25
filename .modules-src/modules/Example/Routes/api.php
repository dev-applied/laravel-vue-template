<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Example\Http\Controllers\ExampleNoteController;

// Registered by the module's ModuleServiceProvider under api/v1 with the
// api middleware group — the final surface is /api/v1/example-notes,
// indistinguishable from an app route.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/example-notes', [ExampleNoteController::class, 'index'])->name('example-notes.index');
    Route::post('/example-notes', [ExampleNoteController::class, 'store'])->name('example-notes.store');
});
