<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\SmsMessaging\Contracts\SmsSender;
use Modules\SmsMessaging\Drivers\LogSmsSender;
use Modules\SmsMessaging\Models\SmsMessage;
use Modules\SmsMessaging\Models\SmsOptOut;
use Modules\SmsMessaging\Support\PhoneNumber;
use Modules\SmsMessaging\Support\SmsManager;

beforeEach(function () {
    // Pin the driver rather than inheriting whichever variant this build
    // installed. What is under test here is SmsManager — normalise, check the
    // opt-out list, log — and that is the same behaviour on every driver.
    // Without this, the two tests that name a driver went red on the
    // `driver=twilio` leg, which is a variant CI builds and would have failed.
    app()->instance(SmsSender::class, new LogSmsSender);
    app()->forgetInstance(SmsManager::class);
});

test('a number is normalised to E.164 before anything else touches it', function (string $input, ?string $expected) {
    expect(PhoneNumber::normalise($input, '1'))->toBe($expected);
})->with([
    ['+15551234567', '+15551234567'],
    ['(555) 123-4567', '+15551234567'],
    ['555-123-4567', '+15551234567'],
    ['5551234567', '+15551234567'],
    ['15551234567', '+15551234567'],
    ['+44 20 7946 0958', '+442079460958'],
    ['12345', null],
    ['not a number', null],
    ['', null],
]);

test('an opt-out matches however the number was typed', function () {
    // The failure this prevents: somebody texts STOP from +15551234567, the app
    // later sends to "(555) 123-4567" because that is what a form captured, a
    // string comparison says they differ, and a message goes to somebody who
    // asked not to receive one. In the US that is a legal problem.
    SmsOptOut::add('+1 (555) 123-4567', 'Replied STOP');

    expect(SmsOptOut::has('5551234567'))->toBeTrue()
        ->and(SmsOptOut::has('+15551234567'))->toBeTrue()
        ->and(SmsOptOut::has('555.123.4567'))->toBeTrue()
        ->and(SmsOptOut::has('+15559999999'))->toBeFalse();
});

test('a suppressed send is recorded and reported as not sent', function () {
    // Not silently dropped: the log is what somebody reads when asked "why
    // didn't they get it", and an invisible refusal is the worst possible
    // answer to that question.
    SmsOptOut::add('+15551234567');

    $message = app(SmsManager::class)->send('+15551234567', 'Your order shipped.');

    expect($message->status)->toBe(SmsMessage::STATUS_SUPPRESSED)
        ->and($message->error)->toContain('opted out')
        ->and(SmsMessage::query()->count())->toBe(1);
});

test('a good send is accepted and logged', function () {
    $message = app(SmsManager::class)->send('555-123-4567', 'Hello');

    expect($message->status)->toBe(SmsMessage::STATUS_ACCEPTED)
        ->and($message->phone_number)->toBe('+15551234567', 'the log must store E.164, not what was passed in')
        ->and($message->driver)->toBe('log')
        ->and($message->vendor_id)->not->toBeNull();
});

test('an unusable number fails rather than being handed to a vendor', function () {
    $message = app(SmsManager::class)->send('nonsense', 'Hello');

    expect($message->status)->toBe(SmsMessage::STATUS_FAILED)
        ->and($message->error)->toContain('Unrecognised');
});

test('an unparseable number counts as opted out', function () {
    // The safe direction. The alternative is sending to a number the opt-out
    // list could never have matched.
    expect(SmsOptOut::has('nonsense'))->toBeTrue();
});

test('the log is refused when the ability is not defined', function () {
    // Falls CLOSED. The log holds message bodies and phone numbers, so exposing
    // it has to be an act rather than a default — "any signed-in user" is not a
    // boundary.
    $user = User::factory()->create();

    // And the refusal SAYS something. An empty 403 body left the page showing
    // its "nothing sent yet" empty state, which tells the user there are no
    // messages when the truth is that they may not see them.
    $this->actingAs($user)->getJson('/api/v1/sms/messages')
        ->assertForbidden()
        ->assertJsonPath('message', 'You do not have permission to read the SMS log.');

    $this->actingAs($user)->getJson('/api/v1/sms/opt-outs')->assertForbidden();
});

test('the log is readable once the project grants the ability', function () {
    Gate::define('view-sms-log', fn () => true);

    $user = User::factory()->create();
    app(SmsManager::class)->send('+15551234567', 'Hello');

    $body = $this->actingAs($user)->getJson('/api/v1/sms/messages')->assertOk()->json();

    expect($body['data'][0]['phone_number'])->toBe('+15551234567');
});

test('the log endpoints require a signed-in user', function () {
    $this->getJson('/api/v1/sms/messages')->assertUnauthorized();
    $this->getJson('/api/v1/sms/opt-outs')->assertUnauthorized();
});
