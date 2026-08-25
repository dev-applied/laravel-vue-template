<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Drivers;

use Modules\SmsMessaging\Contracts\SmsSender;
use Modules\SmsMessaging\Support\SmsResult;
use Throwable;

/**
 * Twilio, over its REST API.
 *
 * Deliberately HTTP rather than the SDK. The SDK pulls a large dependency tree
 * for two endpoints, and — more usefully — Laravel's Http fake makes this
 * testable without a network or a vendor double, which the SDK's own client
 * does not.
 *
 * A vendor error is RETURNED, never thrown. SmsManager has to log the attempt
 * either way, and an exception here would lose the record of the thing that
 * failed, which is precisely the one support needs to see.
 */
class TwilioSmsSender implements SmsSender
{
    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $from,
    ) {}

    public function send(string $to, string $body): SmsResult
    {
        if ($this->accountSid === '' || $this->authToken === '' || $this->from === '') {
            return SmsResult::failed('Twilio is not configured — set TWILIO_SID, TWILIO_TOKEN and TWILIO_FROM.');
        }

        try {
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                    'To' => $to,
                    'From' => $this->from,
                    'Body' => $body,
                ]);
        } catch (Throwable $e) {
            return SmsResult::failed($e->getMessage());
        }

        if ($response->successful()) {
            return SmsResult::accepted($response->json('sid'));
        }

        return SmsResult::failed(
            $response->json('message') ?? "Twilio responded {$response->status()}."
        );
    }

    public function name(): string
    {
        return 'twilio';
    }
}
