<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Announcements\Http\Controllers\ActiveAnnouncementController;
use Modules\Announcements\Http\Controllers\AnnouncementController;
use Modules\Announcements\Http\Controllers\PublishAnnouncementController;

Route::middleware('auth:sanctum')->group(function () {
    // Reader side — every signed-in user.
    Route::get('/announcements/active', [ActiveAnnouncementController::class, 'index'])
        ->name('announcements.active');
    Route::post('/announcements/{announcement}/dismiss', [ActiveAnnouncementController::class, 'dismiss'])
        ->name('announcements.dismiss');

    // Authoring side — writes something every user sees, so it is gated.
    Route::middleware('can:manage-announcements')->group(function () {
        Route::apiResource('announcements', AnnouncementController::class);
        Route::post('/announcements/{announcement}/publish', [PublishAnnouncementController::class, 'publish'])
            ->name('announcements.publish');
        Route::post('/announcements/{announcement}/unpublish', [PublishAnnouncementController::class, 'unpublish'])
            ->name('announcements.unpublish');
    });
});
