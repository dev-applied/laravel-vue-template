<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\RolesPermissions\Http\Controllers\PermissionController;
use Modules\RolesPermissions\Http\Controllers\RoleController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::apiResource('roles', RoleController::class);
});
