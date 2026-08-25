<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SmsMessaging\Support\PhoneNumber;

/**
 * Numbers that have asked not to be texted.
 *
 * In the US honouring STOP is a legal obligation under the TCPA and the CTIA
 * guidelines, not a courtesy — which is the whole argument for this living in a
 * module once rather than being re-derived per client. Every send goes through
 * SmsManager, and SmsManager checks here first.
 *
 * @property string $phone_number
 * @property string|null $reason
 */
class SmsOptOut extends Model
{
    protected $table = 'sms_opt_outs';

    protected $fillable = ['phone_number', 'reason'];

    public static function add(string $number, ?string $reason = null): ?self
    {
        $normalised = PhoneNumber::normalise($number);

        if ($normalised === null) {
            return null;
        }

        return static::query()->updateOrCreate(
            ['phone_number' => $normalised],
            ['reason' => $reason],
        );
    }

    /** START / UNSTOP — the opposite keyword, and it has to work or STOP is a trap. */
    public static function remove(string $number): void
    {
        $normalised = PhoneNumber::normalise($number);

        if ($normalised === null) {
            return;
        }

        static::query()->where('phone_number', $normalised)->delete();
    }

    public static function has(string $number): bool
    {
        $normalised = PhoneNumber::normalise($number);

        // An unparseable number counts as opted out. Refusing to send somewhere
        // we cannot identify is the safe direction: the alternative is sending
        // to a number the opt-out list could never have matched.
        if ($normalised === null) {
            return true;
        }

        return static::query()->where('phone_number', $normalised)->exists();
    }
}
