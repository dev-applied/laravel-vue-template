<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\SmsMessaging\Drivers\TwilioSmsSender;
use Modules\SmsMessaging\Models\SmsMessage;
use Modules\SmsMessaging\Support\SmsManager;

test('it posts to Twilio and reports the message sid', function () {
    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
    ]);

    $result = (new TwilioSmsSender('AC123', 'token', '+15550000000'))->send('+15551234567', 'Hello');

    expect($result->accepted)->toBeTrue()
        ->and($result->vendorId)->toBe('SM123');

    Http::assertSent(fn ($request) => $request['To'] === '+15551234567'
        && $request['From'] === '+15550000000'
        && $request['Body'] === 'Hello');
});

test('a vendor error is returned, never thrown', function () {
    // SmsManager has to log the attempt either way. An exception here loses the
    // record of the thing that failed, which is exactly what support needs.
    Http::fake([
        'api.twilio.com/*' => Http::response(['message' => 'The From number is not a valid phone number.'], 400),
    ]);

    $result = (new TwilioSmsSender('AC123', 'token', '+15550000000'))->send('+15551234567', 'Hello');

    expect($result->accepted)->toBeFalse()
        ->and($result->error)->toContain('not a valid phone number');
});

test('missing credentials fail cleanly rather than posting nowhere', function () {
    Http::fake();

    $result = (new TwilioSmsSender('', '', ''))->send('+15551234567', 'Hello');

    expect($result->accepted)->toBeFalse()
        ->and($result->error)->toContain('not configured');

    Http::assertNothingSent();
});

test('a failed vendor call is still logged by the manager', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['message' => 'nope'], 500)]);

    config()->set('sms.driver', 'twilio');
    config()->set('sms.twilio', ['sid' => 'AC123', 'token' => 't', 'from' => '+15550000000']);
    app()->forgetInstance(Modules\SmsMessaging\Contracts\SmsSender::class);
    app()->forgetInstance(SmsManager::class);

    $message = app(SmsManager::class)->send('+15551234567', 'Hello');

    expect($message->status)->toBe(SmsMessage::STATUS_FAILED)
        ->and($message->driver)->toBe('twilio')
        ->and($message->error)->toContain('nope');
});
