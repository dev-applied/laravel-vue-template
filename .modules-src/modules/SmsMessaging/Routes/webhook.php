<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\SmsMessaging\Http\Controllers\InboundSmsController;

// No auth: this is the carrier posting, not a user. It is also excluded from
// CSRF by virtue of being an api-middleware route. Restrict it at the edge by
// vendor IP or a signed URL — see the README; the module cannot know which.
Route::post('sms/inbound', InboundSmsController::class)->name('sms.inbound');
