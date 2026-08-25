<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Contracts;

use Modules\SmsMessaging\Support\SmsResult;

/**
 * The one thing a vendor has to implement.
 *
 * Deliberately narrow: a number, a body, a result. Everything a project
 * actually argues about — opt-out, logging, retries, which number to send from
 * — lives in SmsManager, so swapping Twilio for anyone else is one class and no
 * behavioural change.
 */
interface SmsSender
{
    /** @param  string  $to  E.164, e.g. +15551234567 */
    public function send(string $to, string $body): SmsResult;

    /** Vendor name, recorded on every logged message so a mixed history stays readable. */
    public function name(): string;
}
