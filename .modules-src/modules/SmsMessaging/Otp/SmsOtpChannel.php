<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Otp;

use Modules\SmsMessaging\Support\SmsManager;

/**
 * Closes the loop the Otp module left open.
 *
 * Otp ships email and declares `otp.channel.sms` as a container binding a
 * project provides — it deliberately knows nothing about a vendor. This binds
 * it, so installing both modules gives you SMS codes with no glue code, and
 * every code goes through SmsManager and therefore respects the opt-out list.
 *
 * Not typed against Otp's interface on purpose: this class must load whether or
 * not the Otp module is installed, and referencing an interface from an absent
 * module fatals at container-resolution time. The binding is only registered
 * when Otp is present — see ModuleServiceProvider.
 */
class SmsOtpChannel
{
    public function __construct(private readonly SmsManager $manager) {}

    public function send(string $identifier, string $code): void
    {
        $template = config('sms.otp_template', 'Your verification code is :code');

        $this->manager->send($identifier, str_replace(':code', $code, $template));
    }
}
