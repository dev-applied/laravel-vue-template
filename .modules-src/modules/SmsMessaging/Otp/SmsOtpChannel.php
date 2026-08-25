<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Otp;

use Modules\Otp\Channels\OtpChannel;
use Modules\SmsMessaging\Support\PhoneNumber;
use Modules\SmsMessaging\Support\SmsManager;

/**
 * Closes the loop the Otp module left open.
 *
 * Otp ships email and declares `otp.channel.sms` as a container binding a
 * project provides — it deliberately knows nothing about a vendor. This binds
 * it, so installing both modules gives you SMS codes with no glue code, and
 * every code goes through SmsManager and therefore respects the opt-out list.
 *
 * It DOES implement Otp's interface. An earlier version did not, reasoning that
 * the class must load whether or not Otp is installed — and the result was a
 * 500 on every SMS code request, because `ChannelRegistry::get()` returns
 * `OtpChannel` and a TypeError is what a wrong return type produces. The
 * earlier version's own test missed it by calling this class directly instead
 * of going through the registry the application uses.
 *
 * Loading without Otp is not a problem in practice: PHP resolves a parent
 * interface only when the class is first used, and ModuleServiceProvider binds
 * this only when `modules/Otp` is on disk.
 */
class SmsOtpChannel implements OtpChannel
{
    public function __construct(private readonly SmsManager $manager) {}

    public function send(string $identifier, string $code, string $purpose = 'login'): void
    {
        $template = config('sms.otp_template', 'Your verification code is :code');

        // The handset gets the digits; the log gets the shape of the message
        // and nothing usable. Codes are short-lived and single-use, but the log
        // is read by support while a code is still live, and "we could read
        // your code" is not a thing to have to explain.
        $this->manager->send(
            $identifier,
            str_replace(':code', $code, $template),
            logBody: str_replace(':code', str_repeat('•', mb_strlen($code)), $template),
        );
    }

    /**
     * Only a number this module can put into E.164 — the same test the opt-out
     * list uses, so a number that cannot be matched against the list is never
     * accepted as an OTP destination in the first place.
     */
    public function supports(string $identifier): bool
    {
        return PhoneNumber::normalise($identifier) !== null;
    }

    /**
     * Last two digits only. The confirmation screen is shown to whoever typed
     * the number, who may not be its owner.
     */
    public function mask(string $identifier): string
    {
        $number = PhoneNumber::normalise($identifier) ?? $identifier;
        $tail   = mb_substr($number, -2);

        return str_repeat('•', max(mb_strlen($number) - 2, 0)).$tail;
    }
}
