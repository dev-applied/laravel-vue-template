<?php

declare(strict_types=1);

use Modules\Otp\Support\ChannelRegistry;
use Modules\SmsMessaging\Contracts\SmsSender;
use Modules\SmsMessaging\Drivers\LogSmsSender;
use Modules\SmsMessaging\Models\SmsMessage;
use Modules\SmsMessaging\Models\SmsOptOut;
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
    // Resolved THROUGH the registry, not straight out of the container. Going
    // direct is how the first version of this test passed while every real SMS
    // code request 500'd: ChannelRegistry::get() returns `OtpChannel`, and the
    // bridge did not implement it, so the typed return threw a TypeError on the
    // one path the application actually uses.
    $channel = app(ChannelRegistry::class)->get('sms');

    expect($channel)->toBeInstanceOf(SmsOtpChannel::class)
        ->and($channel)->toBeInstanceOf(Modules\Otp\Channels\OtpChannel::class);
});

test('the whole request path works, not just the class', function () {
    // The end-to-end assertion the direct call could never make.
    $this->postJson('/api/v1/otp/request', ['identifier' => '+15551234567'])->assertOk();

    expect(SmsMessage::query()->latest('id')->first()->phone_number)->toBe('+15551234567');
});

test('it rejects an identifier it cannot put into E.164', function () {
    // The same test the opt-out list uses, so a number that could never be
    // matched against that list is not accepted as a destination either.
    $channel = app(ChannelRegistry::class)->get('sms');

    expect($channel->supports('+15551234567'))->toBeTrue()
        ->and($channel->supports('555-123-4567'))->toBeTrue()
        ->and($channel->supports('nonsense'))->toBeFalse();
});

test('the mask shows two digits and no more', function () {
    // The confirmation screen is shown to whoever typed the number, who may not
    // be its owner.
    $masked = app(ChannelRegistry::class)->get('sms')->mask('+15551234567');

    expect($masked)->toEndWith('67')
        ->and($masked)->not->toContain('5551234')
        // mb_strlen, not strlen: the mask character is a multibyte bullet, so
        // a byte count says 32 where the visible length is 12.
        ->and(mb_strlen($masked))->toBe(mb_strlen('+15551234567'));
});

test('a code sent over SMS goes through the manager, so it is logged', function () {
    app(ChannelRegistry::class)->get('sms')->send('+15551234567', '123456', 'login');

    $message = SmsMessage::query()->latest('id')->first();

    expect($message->phone_number)->toBe('+15551234567')
        ->and($message->status)->toBe(SmsMessage::STATUS_ACCEPTED)
        // The handset gets the digits; the log does not. Support reads this
        // table while a code is still live, and a code is a credential.
        ->and($message->body)->not->toContain('123456')
        ->and($message->body)->toContain('••••••');
});

test('a code is NOT sent to somebody who has opted out', function () {
    // The reason the bridge routes through SmsManager rather than calling a
    // driver: an OTP is still an A2P message, and "they asked us to stop" does
    // not stop applying because the message happens to be a login code.
    SmsOptOut::add('+15551234567', 'Replied STOP');

    app(ChannelRegistry::class)->get('sms')->send('+15551234567', '123456', 'login');

    $message = SmsMessage::query()->latest('id')->first();

    expect($message->status)->toBe(SmsMessage::STATUS_SUPPRESSED);
});
