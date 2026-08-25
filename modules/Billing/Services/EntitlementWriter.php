<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Models\UserEntitlement;

/**
 * The only thing that writes resolved access.
 */
class EntitlementWriter
{
    /**
     * @param  array<string, mixed>  $patch
     * @param  int|null  $asOfMs  When the state in $patch was true, in milliseconds.
     *                            For an event-derived patch this is the event's own
     *                            `event_timestamp_ms`. For a patch read live from the
     *                            RevenueCat API it is NOW, because that is when the
     *                            data was true — ordering an API read by the timestamp
     *                            of the event that prompted it would discard current
     *                            truth in favour of a stale replay. Null means the
     *                            write cannot be placed in time at all.
     */
    public function apply(string $appUserId, array $patch, ?int $asOfMs = null): ?UserEntitlement
    {
        $user = $this->resolveUser($appUserId);

        if ($user === null) {
            return null;
        }

        $patch = $this->castTimestamps($patch);

        $entitlement = UserEntitlement::firstOrNew(['user_id' => $user->getKey()]);

        // ── Ordering ─────────────────────────────────────────────────────────
        // Webhooks are not a stream. RevenueCat retries each event on its own
        // schedule, so a delivery that failed twice arrives after events that
        // happened later — and last-write-wins turns that into a paying
        // customer sitting at tier=free with no way to tell why. The reverse
        // is worse and quieter: a stale RENEWAL landing after a REFUND
        // restores access that was taken away on purpose.
        //
        // Strictly older is refused; equal is applied. Two events can share a
        // millisecond (a transfer emits a pair), and ordering cannot separate
        // those anyway, so refusing them would drop a real change to buy
        // nothing.
        if ($asOfMs !== null && $entitlement->last_event_at_ms !== null && $asOfMs < $entitlement->last_event_at_ms) {
            Log::info('billing: ignored an out-of-order event', [
                'user_id'      => $user->getKey(),
                'event_at'     => $asOfMs,
                'resolved_at'  => $entitlement->last_event_at_ms,
                'behind_by_ms' => $entitlement->last_event_at_ms - $asOfMs,
            ]);

            return $entitlement->exists ? $entitlement : null;
        }

        // trial_used is sticky. Once someone has had a trial, later events that
        // say nothing about it must not quietly reset the flag — it is the only
        // thing separating "never subscribed" from "trial expired".
        if ($entitlement->trial_used && ! array_key_exists('trial_used', $patch)) {
            unset($patch['trial_used']);
        }

        $entitlement->fill($patch);
        $entitlement->user_id = $user->getKey();

        // Set outside fill() on purpose: the watermark is not in $fillable, so
        // a patch can never move it. Only a placeable write advances it — an
        // unplaceable one applies but leaves the mark where it was, so it
        // cannot discard the real events still in flight behind it.
        if ($asOfMs !== null) {
            $entitlement->last_event_at_ms = max($asOfMs, (int) $entitlement->last_event_at_ms);
        } else {
            Log::warning('billing: applied an entitlement write that carries no event time', [
                'user_id' => $user->getKey(),
            ]);
        }

        $entitlement->save();

        return $entitlement->fresh();
    }

    /**
     * The app user id IS our own user id (rule 2). An id we cannot resolve is
     * returned as null rather than guessed at — attributing a purchase to the
     * wrong account is worse than not attributing it.
     */
    public function resolveUser(string $appUserId): ?User
    {
        if (is_numeric($appUserId)) {
            return User::find((int) $appUserId);
        }

        return User::where('email', mb_strtolower($appUserId))->first();
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function castTimestamps(array $patch): array
    {
        foreach (['current_period_end', 'trial_ends_at'] as $key) {
            if (isset($patch[$key]) && is_int($patch[$key])) {
                // RevenueCat sends milliseconds. Passing them to Carbon as
                // seconds lands the expiry in the year 57000 and the
                // subscription never expires.
                $patch[$key] = Carbon::createFromTimestampMs($patch[$key]);
            }
        }

        return $patch;
    }
}
