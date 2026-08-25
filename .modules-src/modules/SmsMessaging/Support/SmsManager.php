<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\SmsMessaging\Contracts\SmsSender;
use Modules\SmsMessaging\Models\SmsMessage;
use Modules\SmsMessaging\Models\SmsOptOut;

/**
 * The front door. Nothing should call a driver directly.
 *
 * Everything that must happen on EVERY send lives here rather than in each
 * vendor driver, because a rule enforced per-driver is a rule that is missing
 * the day somebody adds the second driver:
 *
 *   normalise the number      so the opt-out list can match it at all
 *   check the opt-out list    a legal obligation in the US, not a courtesy
 *   log the attempt           including the refusals, which are the ones
 *                             support asks about
 *
 * A suppressed send is recorded and reported as NOT sent. Silently dropping it
 * would make the opt-out list invisible in exactly the situation somebody is
 * trying to explain.
 */
class SmsManager
{
    public function __construct(private readonly SmsSender $sender) {}

    /**
     * @param  string|null  $logBody  what to RECORD, when it must differ from what is sent.
     *                                A one-time code is the case that forces this to exist:
     *                                the handset needs the digits, the delivery log does not,
     *                                and a support person reading the log should not be able
     *                                to use a live code. Redaction happens here rather than in
     *                                each caller for the same reason the opt-out check does —
     *                                a rule enforced per-caller is missing at the next caller.
     */
    public function send(string $to, string $body, ?Model $notifiable = null, ?string $logBody = null): SmsMessage
    {
        $normalised = PhoneNumber::normalise($to);

        if ($normalised === null) {
            return $this->record($to, $logBody ?? $body, SmsMessage::STATUS_FAILED, error: 'Unrecognised phone number.', notifiable: $notifiable);
        }

        if (SmsOptOut::has($normalised)) {
            return $this->record($normalised, $logBody ?? $body, SmsMessage::STATUS_SUPPRESSED, error: 'Recipient has opted out.', notifiable: $notifiable);
        }

        $result = $this->sender->send($normalised, $body);

        return $this->record(
            $normalised,
            $logBody ?? $body,
            $result->accepted ? SmsMessage::STATUS_ACCEPTED : SmsMessage::STATUS_FAILED,
            vendorId: $result->vendorId,
            error: $result->error,
            notifiable: $notifiable,
        );
    }

    public function driverName(): string
    {
        return $this->sender->name();
    }

    private function record(
        string $number,
        string $body,
        string $status,
        ?string $vendorId = null,
        ?string $error = null,
        ?Model $notifiable = null,
    ): SmsMessage {
        return SmsMessage::query()->create([
            'phone_number'    => $number,
            'body'            => $body,
            'status'          => $status,
            'driver'          => $this->sender->name(),
            'vendor_id'       => $vendorId,
            'error'           => $error,
            'notifiable_type' => $notifiable?->getMorphClass(),
            'notifiable_id'   => $notifiable?->getKey(),
        ]);
    }
}
