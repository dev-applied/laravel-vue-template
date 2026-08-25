<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Support;

/**
 * What a driver reports back.
 *
 * `accepted` means the vendor took the message, NOT that a handset received it
 * — carrier delivery is asynchronous and arrives later on a status webhook, if
 * at all. Conflating the two is how a "sent" column ends up meaning nothing.
 */
class SmsResult
{
    private function __construct(
        public readonly bool $accepted,
        public readonly ?string $vendorId = null,
        public readonly ?string $error = null,
    ) {}

    public static function accepted(?string $vendorId = null): self
    {
        return new self(true, $vendorId);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
