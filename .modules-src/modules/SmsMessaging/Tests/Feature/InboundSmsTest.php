<?php

declare(strict_types=1);

use Modules\SmsMessaging\Models\SmsOptOut;

test('STOP opts the sender out, in every spelling carriers deliver', function (string $keyword) {
    $this->post('/api/v1/sms/inbound', ['From' => '+15551234567', 'Body' => $keyword])
        ->assertOk();

    expect(SmsOptOut::has('+15551234567'))->toBeTrue();
})->with(['STOP', 'stop', ' Stop ', 'STOPALL', 'unsubscribe', 'CANCEL', 'End', 'quit', 'STOP.']);

test('STOP is answered with an empty TwiML document, not a confirmation', function () {
    // The carrier sends its own confirmation. Adding ours is both a duplicate
    // and a message to somebody who has just said stop sending messages.
    $response = $this->post('/api/v1/sms/inbound', ['From' => '+15551234567', 'Body' => 'STOP'])->assertOk();

    expect($response->getContent())->toContain('<Response/>')
        ->and($response->getContent())->not->toContain('<Message>');
});

test('START undoes it, or STOP would be a trap', function () {
    SmsOptOut::add('+15551234567');

    $response = $this->post('/api/v1/sms/inbound', ['From' => '+15551234567', 'Body' => 'START'])->assertOk();

    expect(SmsOptOut::has('+15551234567'))->toBeFalse()
        ->and($response->getContent())->toContain('<Message>');
});

test('HELP replies, and the reply says how to opt out', function () {
    // A carrier requirement, not a nicety.
    $response = $this->post('/api/v1/sms/inbound', ['From' => '+15551234567', 'Body' => 'HELP'])->assertOk();

    expect(strtoupper($response->getContent()))->toContain('STOP');
});

test('an unknown keyword changes nothing and still answers 200', function () {
    $this->post('/api/v1/sms/inbound', ['From' => '+15551234567', 'Body' => 'hello there'])->assertOk();

    expect(SmsOptOut::has('+15551234567'))->toBeFalse();
});

test('an unusable From is answered 200, not rejected', function () {
    // A 4xx makes the vendor retry a webhook that can never succeed, and some
    // vendors disable an endpoint that keeps failing.
    $this->post('/api/v1/sms/inbound', ['From' => 'garbage', 'Body' => 'STOP'])->assertOk();

    expect(SmsOptOut::query()->count())->toBe(0);
});

test('the webhook is not behind auth — the carrier is not a user', function () {
    $this->post('/api/v1/sms/inbound', ['From' => '+15551234567', 'Body' => 'STOP'])->assertOk();
});
