<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Support;

/**
 * Normalises a number to E.164 so the opt-out list and the delivery log agree.
 *
 * This matters more than it looks. A user texts STOP from +15551234567; the app
 * later sends to "(555) 123-4567" because that is how somebody typed it into a
 * form. String-comparing those two says they are different numbers, the opt-out
 * silently does not apply, and the failure is a message sent to somebody who
 * asked not to receive one — which in the US is a legal problem, not a bug
 * report.
 *
 * Intentionally not a full libphonenumber. It handles the case this module can
 * be sure about — a bare national number in the configured default country —
 * and refuses anything it cannot place rather than guessing.
 */
class PhoneNumber
{
    public static function normalise(string $number, ?string $defaultCountryCode = null): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $number) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '+')) {
            $rest = preg_replace('/\D/', '', substr($digits, 1)) ?? '';

            return strlen($rest) >= 8 && strlen($rest) <= 15 ? '+'.$rest : null;
        }

        $digits = preg_replace('/\D/', '', $digits) ?? '';
        $code = ltrim((string) ($defaultCountryCode ?? config('sms.default_country_code', '1')), '+');

        // A US 11-digit number beginning with the country code is already whole.
        if ($code !== '' && str_starts_with($digits, $code) && strlen($digits) > 10) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10 && $code !== '') {
            return '+'.$code.$digits;
        }

        return null;
    }
}
