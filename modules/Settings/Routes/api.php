<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\SettingController;

// Public settings are readable signed-out: the app name and a maintenance
// banner are needed before anyone has logged in.
Route::get('/settings/public', [SettingController::class, 'publicIndex'])->name('settings.public');

Route::middleware(['auth:sanctum', 'can:manage-settings'])->group(function () {
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});
