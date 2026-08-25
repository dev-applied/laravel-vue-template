<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Files\Http\Controllers\PresignedUploadController;

// Only loaded when the module was installed with storage=s3-presigned — the
// `local` choice drops this file, and ModuleServiceProvider skips it when absent.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/files/generate-presigned-url', [PresignedUploadController::class, 'generate'])
        ->name('files.presigned.generate');

    Route::put('/files/process/{file}', [PresignedUploadController::class, 'process'])
        ->name('files.presigned.process');
});
