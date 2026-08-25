<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\SmsMessaging\Http\Controllers\SmsLogController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('sms/messages', [SmsLogController::class, 'index'])->name('sms.messages.index');
    Route::get('sms/opt-outs', [SmsLogController::class, 'optOuts'])->name('sms.opt-outs.index');
    Route::delete('sms/opt-outs/{number}', [SmsLogController::class, 'removeOptOut'])
        ->where('number', '.*')
        ->name('sms.opt-outs.destroy');
});
