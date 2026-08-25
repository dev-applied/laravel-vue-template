<?php

declare(strict_types=1);

namespace Modules\Billing\Enums;

enum SubscriptionStatus: string
{
    case None = 'none';

    case Trial = 'trial';

    case Active = 'active';

    /** Auto-renew is off but the paid period has not ended — access continues. */
    case Cancelled = 'cancelled';

    case Lapsed = 'lapsed';

    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Cancelled], true);
    }
}
