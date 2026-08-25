<?php

declare(strict_types=1);

namespace Modules\Auth\Sso;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * A sign-in the resolver refused for an identity reason.
 *
 * Carries TWO messages on purpose. The specific one says which rule fired and
 * goes to the log; the client is only ever shown the generic one plus a
 * reference.
 *
 * The reason: the three refusals this replaces were individually reasonable and
 * collectively an account-existence oracle. "…will not be linked to an existing
 * account", "No account exists for this email address" and "This account has
 * been deactivated" let an unauthenticated caller sort any address into
 * exists / does not exist / deactivated, one callback at a time. On a health
 * or finance product, confirming that a named person holds an account is the
 * disclosure — before anyone gets near a takeover.
 */
class SsoRefused extends RuntimeException
{
    public readonly string $reference;

    public function __construct(string $logMessage, ?string $reference = null)
    {
        parent::__construct($logMessage);

        // Short, case-insensitive, unambiguous when read aloud down a phone to
        // whoever is going to grep the log for it.
        $this->reference = $reference ?: mb_strtoupper(Str::random(8));
    }

    /** What the person who was refused is shown. Deliberately uninformative. */
    public function publicMessage(): string
    {
        return 'We could not sign you in with that account. '
            .'If you believe this is a mistake, contact your administrator and quote reference '
            .$this->reference.'.';
    }
}
