<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Drivers;

use Illuminate\Support\Facades\Log;
use Modules\SmsMessaging\Contracts\SmsSender;
use Modules\SmsMessaging\Support\SmsResult;

/**
 * The default driver: writes the message to the log and accepts it.
 *
 * The default on purpose, so the module installs and its tests run with no
 * account, no credentials and no network. A project that has not configured a
 * vendor yet still gets working opt-out handling and a full delivery log, which
 * is most of the value.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $body): SmsResult
    {
        Log::info('[sms] would send', ['to' => $to, 'body' => $body]);

        return SmsResult::accepted('log-'.substr(md5($to.$body.microtime()), 0, 12));
    }

    public function name(): string
    {
        return 'log';
    }
}
