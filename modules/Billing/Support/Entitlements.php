<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use Modules\Billing\Enums\SubscriptionTier;
use Modules\Billing\Models\UserEntitlement;

/**
 * Resolved access for a user, without requiring anything of the User model.
 *
 * The trait is nicer to read at a call site, but a copy-in module must not
 * depend on a kernel model having been edited — otherwise `module:add`
 * produces something that 500s until a human remembers a manual step. Every
 * gate in this module goes through here; the trait is sugar over it.
 */
class Entitlements
{
    public function for(mixed $user): UserEntitlement
    {
        if ($user === null) {
            return new UserEntitlement();
        }

        // Never null. A user with no row is a FREE user, not an error — a null
        // here turns every gate into a null check that someone eventually
        // forgets.
        return UserEntitlement::firstWhere('user_id', $user->getKey())
            ?? new UserEntitlement(['user_id' => $user->getKey()]);
    }

    public function isActive(mixed $user): bool
    {
        return $this->for($user)->isActive();
    }

    public function hasTier(mixed $user, SubscriptionTier|string $tier): bool
    {
        $tier = $tier instanceof SubscriptionTier ? $tier : SubscriptionTier::from($tier);

        return $this->for($user)->hasTier($tier);
    }
}
