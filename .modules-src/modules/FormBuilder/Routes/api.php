<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\FormBuilder\Http\Controllers\FormController;
use Modules\FormBuilder\Http\Controllers\FormSubmissionController;

// Filling a form in. Public forms need no account; the controller enforces
// which is which. Throttled — a public form is an open write endpoint.
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/forms/{slug}/render', [FormSubmissionController::class, 'show'])->name('forms.render');
    Route::post('/forms/{slug}/submit', [FormSubmissionController::class, 'store'])->name('forms.submit');
});

Route::middleware(['auth:sanctum', 'can:manage-forms'])->group(function () {
    Route::get('/forms', [FormController::class, 'index'])->name('forms.index');
    Route::post('/forms', [FormController::class, 'store'])->name('forms.store');
    Route::get('/forms/{form}', [FormController::class, 'show'])->name('forms.show');
    Route::put('/forms/{form}', [FormController::class, 'update'])->name('forms.update');
    Route::delete('/forms/{form}', [FormController::class, 'destroy'])->name('forms.destroy');

    Route::get('/forms/{form}/submissions', [FormSubmissionController::class, 'index'])->name('forms.submissions');
});
