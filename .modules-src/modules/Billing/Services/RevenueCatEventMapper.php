<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use Modules\Billing\Enums\PaymentProvider;
use Modules\Billing\Enums\SubscriptionPlan;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Enums\SubscriptionTier;

/**
 * Turns a RevenueCat webhook payload into a state change.
 *
 * Deliberately pure — no IO, no clock. Two reasons: it can be unit-tested
 * against captured payloads, and it can run BEFORE the idempotency ledger is
 * claimed, which is what stops an ignored event permanently burning its own
 * event id.
 */
final class RevenueCatEventMapper
{
    /**
     * One shared "no access" patch, used on EVERY path that takes access away.
     *
     * A revocation that clears the tier but forgets an expiry column leaves a
     * ghost entitlement that some other code path will honour.
     *
     * @var array<string, mixed>
     */
    public const REVOKED = [
        'tier'                     => 'free',
        'status'                   => 'lapsed',
        'plan'                     => 'none',
        'cancel_at_period_end'     => false,
        'current_period_end'       => null,
        'provider_subscription_id' => null,
    ];

    /**
     * TRANSFER is deliberately absent from this list.
     *
     * It looks like an activation, so it lands here naturally — but its payload
     * carries no product, no entitlement ids and no expiry, so tier resolution
     * finds nothing and falls through to `free`, wiping the account that just
     * RECEIVED the subscription. It gets its own branch.
     */
    private const ACTIVATING = [
        'INITIAL_PURCHASE', 'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE',
        'SUBSCRIPTION_EXTENDED', 'NON_RENEWING_PURCHASE', 'REFUND_REVERSED',
        'TEMPORARY_ENTITLEMENT_GRANT',
    ];

    private const REVOKING = ['EXPIRATION', 'REFUND', 'SUBSCRIPTION_PAUSED'];

    /**
     * @param  array<string, mixed>  $event
     */
    public function map(array $event): MappedEvent
    {
        $type      = mb_strtoupper((string) ($event['type'] ?? ''));
        $isSandbox = mb_strtoupper((string) ($event['environment'] ?? '')) === 'SANDBOX';
        $eventAtMs = $this->eventAtMs($event);

        if ($type === 'TRANSFER') {
            return MappedEvent::transfer(
                to: array_values(array_filter((array) ($event['transferred_to'] ?? []))),
                from: array_values(array_filter((array) ($event['transferred_from'] ?? []))),
                isSandbox: $isSandbox,
                eventAtMs: $eventAtMs,
            );
        }

        $userId = $event['app_user_id'] ?? null;

        if (! $userId) {
            return MappedEvent::inert($isSandbox, 'no attributable user');
        }

        $provider = PaymentProvider::fromStore($event['store'] ?? null);

        if (in_array($type, self::ACTIVATING, true)) {
            return MappedEvent::grant((string) $userId, $this->grantPatch($event, $provider), $isSandbox, $eventAtMs);
        }

        if (in_array($type, self::REVOKING, true)) {
            return MappedEvent::revoke((string) $userId, self::REVOKED, $isSandbox, $eventAtMs);
        }

        if ($type === 'CANCELLATION') {
            // Cancellation means auto-renew off, NOT loss of access. Downgrading
            // here robs a customer of time they already paid for.
            return MappedEvent::cancel((string) $userId, [
                'status'               => SubscriptionStatus::Cancelled->value,
                'cancel_at_period_end' => true,
            ], $isSandbox, $eventAtMs);
        }

        if ($type === 'BILLING_ISSUE') {
            // Record it, change nothing. The store runs its own grace period and
            // sends an expiration if it truly lapses; revoking here cuts off a
            // customer whose card retry is about to succeed.
            return MappedEvent::inert($isSandbox, 'billing issue recorded');
        }

        // Vendors add event types over time. An unrecognised one must never
        // corrupt a paying customer's state.
        return MappedEvent::inert($isSandbox, 'unrecognised event type');
    }

    /**
     * The vendor's clock for this event, in milliseconds.
     *
     * Null when the payload carried none, or carried something that is not a
     * number. Null means "unplaceable in time", which is deliberately NOT the
     * same as "now": treating it as now would let one malformed event advance
     * the watermark past every real event still in flight behind it.
     *
     * @param  array<string, mixed>  $event
     */
    private function eventAtMs(array $event): ?int
    {
        $raw = $event['event_timestamp_ms'] ?? null;

        if (! is_numeric($raw)) {
            return null;
        }

        $ms = (int) $raw;

        return $ms > 0 ? $ms : null;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function grantPatch(array $event, PaymentProvider $provider): array
    {
        $entitlements = (array) ($event['entitlement_ids'] ?? []);
        $product      = (string) ($event['product_id'] ?? '');

        // Entitlement identifiers FIRST — they are what you configured to mean
        // "has premium". The SKU is a free-form store string and only a
        // fallback.
        $tier = SubscriptionTier::fromIdentifiers(implode(' ', $entitlements), $product);
        $plan = SubscriptionPlan::fromIdentifiers($product, implode(' ', $entitlements));

        $periodType = mb_strtoupper((string) ($event['period_type'] ?? ''));
        $isTrial    = $periodType === 'TRIAL';

        $patch = [
            'tier'                 => $tier->value,
            'status'               => $isTrial ? SubscriptionStatus::Trial->value : SubscriptionStatus::Active->value,
            'plan'                 => $plan->value,
            'provider'             => $provider->value,
            'cancel_at_period_end' => false,
        ];

        if (isset($event['expiration_at_ms'])) {
            $patch['current_period_end'] = (int) $event['expiration_at_ms'];
        }

        if ($isTrial) {
            // Sticky once true: it is what distinguishes "never subscribed"
            // from "trial expired" long after the trial has gone.
            $patch['trial_used'] = true;

            if (isset($event['expiration_at_ms'])) {
                $patch['trial_ends_at'] = (int) $event['expiration_at_ms'];
            }
        }

        if (isset($event['transaction_id'])) {
            $patch['provider_subscription_id'] = (string) $event['transaction_id'];
        }

        return $patch;
    }
}
