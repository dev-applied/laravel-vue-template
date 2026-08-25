<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Billing\Enums\SubscriptionTier;
use Modules\Billing\Models\UserEntitlement;

/**
 * OPTIONAL sugar for the User model.
 *
 *   class User extends Authenticatable
 *   {
 *       use HasEntitlement;
 *   }
 *
 * The module works fully without it — every gate resolves through the
 * Entitlements service — so a fresh `module:add` is never broken waiting on a
 * manual edit. Add this when you want `$user->hasTier('premium')` at call
 * sites instead of injecting the service.
 */
trait HasEntitlement
{
    /** @return HasOne<UserEntitlement, self> */
    public function entitlement(): HasOne
    {
        return $this->hasOne(UserEntitlement::class);
    }

    public function currentEntitlement(): UserEntitlement
    {
        return app(Entitlements::class)->for($this);
    }

    public function subscribed(): bool
    {
        return app(Entitlements::class)->isActive($this);
    }

    public function hasTier(SubscriptionTier|string $tier): bool
    {
        return app(Entitlements::class)->hasTier($this, $tier);
    }
}
