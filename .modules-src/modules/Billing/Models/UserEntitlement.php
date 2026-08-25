<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Billing\Enums\PaymentProvider;
use Modules\Billing\Enums\SubscriptionPlan;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Enums\SubscriptionTier;

class UserEntitlement extends Model
{
    protected $fillable = [
        'user_id', 'tier', 'status', 'plan', 'provider', 'provider_subscription_id',
        'trial_ends_at', 'current_period_end', 'cancel_at_period_end', 'trial_used',
    ];

    protected $casts = [
        'tier'                 => SubscriptionTier::class,
        'status'               => SubscriptionStatus::class,
        'plan'                 => SubscriptionPlan::class,
        'provider'             => PaymentProvider::class,
        'trial_ends_at'        => 'datetime',
        'current_period_end'   => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'trial_used'           => 'boolean',
    ];

    protected $attributes = [
        'tier'     => 'free',
        'status'   => 'none',
        'plan'     => 'none',
        'provider' => 'none',
    ];

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this entitlement grants access right now.
     *
     * Both the status AND the clock. A cancelled subscription keeps access
     * until its period ends; a lapsed one never has it; and a row whose period
     * end has quietly passed without an expiration webhook arriving must not
     * keep granting.
     */
    public function isActive(): bool
    {
        if (! $this->status->grantsAccess()) {
            return false;
        }

        return $this->current_period_end === null || $this->current_period_end->isFuture();
    }

    public function hasTier(SubscriptionTier $tier): bool
    {
        return $this->isActive() && $this->tier->atLeast($tier);
    }

    /**
     * "Never subscribed" and "trial expired" are different states that need
     * different copy and a different wall.
     */
    public function isFirstTime(): bool
    {
        return ! $this->trial_used && $this->status === SubscriptionStatus::None;
    }
}
