<?php

declare(strict_types=1);

use Modules\SmsMessaging\Models\SmsMessage;
use Modules\SmsMessaging\Models\SmsOptOut;
use Modules\SmsMessaging\Contracts\SmsSender;
use Modules\SmsMessaging\Drivers\LogSmsSender;
use Modules\SmsMessaging\Otp\SmsOtpChannel;
use Modules\SmsMessaging\Support\SmsManager;

beforeEach(function () {
    // Same reason as SmsMessagingTest: the bridge routing through SmsManager is
    // what is under test, not which vendor a build happens to configure.
    app()->instance(SmsSender::class, new LogSmsSender);
    app()->forgetInstance(SmsManager::class);

    if (! is_dir(base_path('modules/Otp'))) {
        // A real environment fact, checked directly rather than inferred from a
        // failure — the binding is only registered when Otp is on disk.
        $this->markTestSkipped('The Otp module is not installed in this build.');
    }
});

test('installing both modules binds the SMS channel Otp declares', function () {
    // Otp ships email and declares `otp.channel.sms` as a binding a project
    // provides; it deliberately knows nothing about a vendor. This is the glue
    // code a project would otherwise write by hand in every build that wants
    // codes over SMS.
    expect(app()->bound('otp.channel.sms'))->toBeTrue()
        ->and(app('otp.channel.sms'))->toBeInstanceOf(SmsOtpChannel::class);
});

test('a code sent over SMS goes through the manager, so it is logged', function () {
    app('otp.channel.sms')->send('+15551234567', '123456');

    $message = SmsMessage::query()->latest('id')->first();

    expect($message->phone_number)->toBe('+15551234567')
        ->and($message->body)->toContain('123456')
        ->and($message->status)->toBe(SmsMessage::STATUS_ACCEPTED);
});

test('a code is NOT sent to somebody who has opted out', function () {
    // The reason the bridge routes through SmsManager rather than calling a
    // driver: an OTP is still an A2P message, and "they asked us to stop" does
    // not stop applying because the message happens to be a login code.
    SmsOptOut::add('+15551234567', 'Replied STOP');

    app('otp.channel.sms')->send('+15551234567', '123456');

    $message = SmsMessage::query()->latest('id')->first();

    expect($message->status)->toBe(SmsMessage::STATUS_SUPPRESSED);
});
