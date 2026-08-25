<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Enums\PaymentProvider;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Enums\SubscriptionTier;
use Throwable;

/**
 * Transfer is not an activation.
 *
 * This one caused a real production bug: a paying customer silently demoted to
 * free. A transfer moves a store subscription between app accounts and LOOKS
 * like an activation, but its payload carries no product id, no entitlement ids
 * and no expiry — so any "what tier is this" function has nothing to read and
 * falls through to the default, wiping the account that just received the
 * subscription.
 *
 * So: attribute by transferred_to / transferred_from (app_user_id is null on
 * these events), and READ THE SUBSCRIBER BACK from the API rather than
 * inferring anything from the payload.
 */
class TransferResolver
{
    public function __construct(private readonly EntitlementWriter $writer) {}

    public function resolve(MappedEvent $mapped): void
    {
        foreach ($mapped->transferredTo as $appUserId) {
            $this->receive((string) $appUserId);
        }

        foreach ($mapped->transferredFrom as $appUserId) {
            // An id on both sides lost nothing.
            if (in_array($appUserId, $mapped->transferredTo, true)) {
                continue;
            }

            // The loss happened when the transfer did, so it is ordered by the
            // event's clock like any other event-derived change.
            $this->writer->apply((string) $appUserId, RevenueCatEventMapper::REVOKED, $mapped->eventAtMs);
        }
    }

    private function receive(string $appUserId): void
    {
        $subscriber = $this->fetchSubscriber($appUserId);

        // Three outcomes, not two: a definite answer, "they own nothing", and
        // "we could not ask". Collapsing the third into the second turns a
        // vendor outage into mass revocation of paying customers. On an
        // unresolvable read, leave state alone and let the retry settle it —
        // a wrong write is silent, a delayed write is not.
        if ($subscriber === null) {
            Log::warning('billing: could not read subscriber during transfer; leaving state alone', [
                'app_user_id' => $appUserId,
            ]);

            return;
        }

        // Both branches below came from a LIVE read, so they are stamped with
        // now(), not with the event's timestamp. The API answered "this is what
        // this subscriber owns" a moment ago; ordering that behind the event
        // that prompted it would let a stale replay overwrite current truth.
        $readAtMs = (int) now()->getPreciseTimestamp(3);

        if ($subscriber === []) {
            $this->writer->apply($appUserId, RevenueCatEventMapper::REVOKED, $readAtMs);

            return;
        }

        $this->writer->apply($appUserId, $this->patchFromSubscriber($subscriber), $readAtMs);
    }

    /**
     * @return array<string, mixed>|null null means "could not ask".
     */
    private function fetchSubscriber(string $appUserId): ?array
    {
        $key = (string) config('billing.secret_api_key', '');

        if ($key === '') {
            Log::error('billing: REVENUECAT_SECRET_API_KEY is not set — a transfer cannot be resolved.');

            return null;
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(10)
                ->get(config('billing.api_base').'/subscribers/'.urlencode($appUserId));
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($response->status() === 404) {
            return [];
        }

        if (! $response->successful()) {
            return null;
        }

        $entitlements = (array) $response->json('subscriber.entitlements', []);

        return array_filter(
            $entitlements,
            fn ($e) => isset($e['expires_date']) === false || strtotime((string) $e['expires_date']) > time()
        );
    }

    /**
     * @param  array<string, mixed>  $entitlements
     * @return array<string, mixed>
     */
    private function patchFromSubscriber(array $entitlements): array
    {
        $identifiers = implode(' ', array_keys($entitlements));
        $products    = implode(' ', array_map(fn ($e) => (string) ($e['product_identifier'] ?? ''), $entitlements));

        $expiries = array_filter(array_map(
            fn ($e) => isset($e['expires_date']) ? strtotime((string) $e['expires_date']) : null,
            $entitlements
        ));

        return [
            'tier'                 => SubscriptionTier::fromIdentifiers($identifiers, $products)->value,
            'status'               => SubscriptionStatus::Active->value,
            'provider'             => PaymentProvider::None->value,
            'cancel_at_period_end' => false,
            'current_period_end'   => $expiries === [] ? null : max($expiries) * 1000,
        ];
    }
}
