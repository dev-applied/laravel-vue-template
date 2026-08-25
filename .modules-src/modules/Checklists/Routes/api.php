<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Checklists\Http\Controllers\ChecklistController;
use Modules\Checklists\Http\Controllers\ChecklistTemplateController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('checklist-templates', [ChecklistTemplateController::class, 'index'])->name('checklist-templates.index');
    Route::post('checklist-templates', [ChecklistTemplateController::class, 'store'])->name('checklist-templates.store');
    Route::post('checklist-templates/{template}/archive', [ChecklistTemplateController::class, 'archive'])
        ->name('checklist-templates.archive');

    Route::get('checklists', [ChecklistController::class, 'index'])->name('checklists.index');
    Route::post('checklists', [ChecklistController::class, 'store'])->name('checklists.store');
    Route::get('checklists/{checklist}', [ChecklistController::class, 'show'])->name('checklists.show');
    Route::patch('checklists/{checklist}/responses/{response}', [ChecklistController::class, 'answer'])
        ->name('checklists.answer');
    Route::post('checklists/{checklist}/complete', [ChecklistController::class, 'complete'])
        ->name('checklists.complete');
});
