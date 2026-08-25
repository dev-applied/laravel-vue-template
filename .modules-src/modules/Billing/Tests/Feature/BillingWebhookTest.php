<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Models\RevenueCatWebhookEvent;
use Modules\Billing\Models\UserEntitlement;

beforeEach(function () {
    config()->set('billing.webhook_secret', 'shhh');
    config()->set('billing.allow_sandbox', false);

    $this->user = User::factory()->create();
});

function post_event(array $overrides = [], string $secret = 'shhh')
{
    $event = array_merge([
        'id'                 => 'evt_'.uniqid(),
        'type'               => 'INITIAL_PURCHASE',
        'app_user_id'        => (string) test()->user->id,
        'store'              => 'APP_STORE',
        'environment'        => 'PRODUCTION',
        'product_id'         => 'app_premium_monthly',
        'entitlement_ids'    => ['premium'],
        'period_type'        => 'NORMAL',
        'expiration_at_ms'   => (int) (now()->addMonth()->timestamp * 1000),
        'event_timestamp_ms' => (int) now()->getPreciseTimestamp(3),
    ], $overrides);

    return test()->withHeader('Authorization', $secret)
        ->postJson('/api/v1/billing/webhook/revenuecat', ['event' => $event]);
}

test('a purchase webhook grants access', function () {
    post_event()->assertOk();

    $entitlement = UserEntitlement::firstOrFail();

    expect($entitlement->tier->value)->toBe('premium')
        ->and($entitlement->isActive())->toBeTrue();
});

test('the client never grants itself anything', function () {
    // There is no endpoint that writes an entitlement. The read is a read.
    $this->actingAs($this->user)
        ->postJson('/api/v1/billing/entitlement', ['tier' => 'premium'])
        ->assertStatus(405);
});

test('an unset webhook secret rejects everything', function () {
    // The one place a permissive default is catastrophic: an open webhook
    // endpoint lets anyone grant themselves any tier.
    config()->set('billing.webhook_secret', '');

    post_event(secret: '')->assertUnauthorized();

    expect(UserEntitlement::count())->toBe(0);
});

test('a wrong secret is rejected', function () {
    post_event(secret: 'guess')->assertUnauthorized();

    expect(UserEntitlement::count())->toBe(0);
});

test('a replayed event is a no-op, not a second grant', function () {
    // RevenueCat retries until it gets a 2xx.
    $id = 'evt_fixed';

    post_event(['id' => $id])->assertOk();
    post_event(['id' => $id, 'type' => 'EXPIRATION'])->assertOk();

    // The replayed id short-circuits before the second payload is handled.
    expect(UserEntitlement::firstOrFail()->tier->value)->toBe('premium')
        ->and(RevenueCatWebhookEvent::count())->toBe(1);
});

test('an ignored event does not burn its event id', function () {
    // THE ordering trap. Claiming the ledger row before deciding to ignore
    // permanently burns that id, and there is no resend button — so a later
    // resend, or a retry after the ignore condition is fixed, is swallowed as
    // a duplicate and the purchase is lost.
    config()->set('billing.allow_sandbox', false);

    post_event(['id' => 'evt_sb', 'environment' => 'SANDBOX'])->assertOk();

    expect(RevenueCatWebhookEvent::where('event_id', 'evt_sb')->exists())->toBeFalse();

    // The same id now works once sandbox granting is switched on.
    config()->set('billing.allow_sandbox', true);
    post_event(['id' => 'evt_sb', 'environment' => 'SANDBOX'])->assertOk();

    expect(UserEntitlement::firstOrFail()->tier->value)->toBe('premium');
});

test('an event with no user does not burn its id either', function () {
    post_event(['id' => 'evt_nouser', 'app_user_id' => null])->assertOk();

    expect(RevenueCatWebhookEvent::where('event_id', 'evt_nouser')->exists())->toBeFalse();
});

test('a sandbox event does not grant production access', function () {
    post_event(['environment' => 'SANDBOX'])->assertOk();

    expect(UserEntitlement::count())->toBe(0);
});

test('cancellation keeps access', function () {
    post_event()->assertOk();
    post_event(['type' => 'CANCELLATION'])->assertOk();

    $entitlement = UserEntitlement::firstOrFail();

    expect($entitlement->isActive())->toBeTrue()
        ->and($entitlement->tier->value)->toBe('premium')
        ->and($entitlement->cancel_at_period_end)->toBeTrue();
});

test('expiration revokes completely', function () {
    post_event()->assertOk();
    post_event(['type' => 'EXPIRATION'])->assertOk();

    $entitlement = UserEntitlement::firstOrFail();

    expect($entitlement->isActive())->toBeFalse()
        ->and($entitlement->tier->value)->toBe('free')
        ->and($entitlement->current_period_end)->toBeNull();
});

test('a billing issue leaves a paying customer alone', function () {
    post_event()->assertOk();
    post_event(['type' => 'BILLING_ISSUE'])->assertOk();

    expect(UserEntitlement::firstOrFail()->isActive())->toBeTrue();
});

test('trial_used survives a later non-trial event', function () {
    // Sticky, because it is the only thing separating "never subscribed" from
    // "trial expired".
    post_event(['period_type' => 'TRIAL'])->assertOk();
    expect(UserEntitlement::firstOrFail()->trial_used)->toBeTrue();

    post_event(['type' => 'EXPIRATION'])->assertOk();

    expect(UserEntitlement::firstOrFail()->trial_used)->toBeTrue();
});

test('the expiry is read as milliseconds', function () {
    // Passing RevenueCat's milliseconds to Carbon as seconds lands the expiry
    // in the year 57000 and the subscription never expires.
    post_event()->assertOk();

    expect(UserEntitlement::firstOrFail()->current_period_end->year)->toBe(now()->year);
});

test('a transfer reads the subscriber back rather than trusting the payload', function () {
    // The payload has no product, entitlement or expiry — inferring from it
    // demotes the account that just received the subscription.
    config()->set('billing.secret_api_key', 'sk_test');

    $recipient = User::factory()->create();

    Http::fake([
        '*/subscribers/*' => Http::response([
            'subscriber' => ['entitlements' => [
                'premium' => ['product_identifier' => 'app_premium_annual', 'expires_date' => now()->addYear()->toIso8601String()],
            ]],
        ]),
    ]);

    $this->withHeader('Authorization', 'shhh')->postJson('/api/v1/billing/webhook/revenuecat', [
        'event' => [
            'id'               => 'evt_transfer', 'type' => 'TRANSFER', 'environment' => 'PRODUCTION',
            'transferred_to'   => [(string) $recipient->id],
            'transferred_from' => [(string) $this->user->id],
        ],
    ])->assertOk();

    expect(UserEntitlement::where('user_id', $recipient->id)->firstOrFail()->tier->value)->toBe('premium');
});

test('a transfer revokes from the losing account', function () {
    config()->set('billing.secret_api_key', 'sk_test');
    post_event()->assertOk();

    $recipient = User::factory()->create();
    Http::fake(['*/subscribers/*' => Http::response(['subscriber' => ['entitlements' => []]], 404)]);

    $this->withHeader('Authorization', 'shhh')->postJson('/api/v1/billing/webhook/revenuecat', [
        'event' => [
            'id'             => 'evt_t2', 'type' => 'TRANSFER',
            'transferred_to' => [(string) $recipient->id], 'transferred_from' => [(string) $this->user->id],
        ],
    ])->assertOk();

    expect(UserEntitlement::where('user_id', $this->user->id)->firstOrFail()->isActive())->toBeFalse();
});

test('an id on both sides of a transfer loses nothing', function () {
    config()->set('billing.secret_api_key', 'sk_test');
    post_event()->assertOk();

    Http::fake(['*/subscribers/*' => Http::response([
        'subscriber' => ['entitlements' => [
            'premium' => ['product_identifier' => 'p', 'expires_date' => now()->addYear()->toIso8601String()],
        ]],
    ])]);

    $this->withHeader('Authorization', 'shhh')->postJson('/api/v1/billing/webhook/revenuecat', [
        'event' => [
            'id'             => 'evt_t3', 'type' => 'TRANSFER',
            'transferred_to' => [(string) $this->user->id], 'transferred_from' => [(string) $this->user->id],
        ],
    ])->assertOk();

    expect(UserEntitlement::firstOrFail()->isActive())->toBeTrue();
});

test('an unreachable api during a transfer leaves state alone', function () {
    // "Could not ask" is not "owns nothing". Collapsing the two turns a vendor
    // outage into mass revocation of paying customers.
    config()->set('billing.secret_api_key', 'sk_test');

    $recipient = User::factory()->create();
    post_event(['app_user_id' => (string) $recipient->id])->assertOk();

    Http::fake(['*/subscribers/*' => Http::response('nope', 503)]);

    $this->withHeader('Authorization', 'shhh')->postJson('/api/v1/billing/webhook/revenuecat', [
        'event' => [
            'id'             => 'evt_t4', 'type' => 'TRANSFER',
            'transferred_to' => [(string) $recipient->id], 'transferred_from' => [],
        ],
    ])->assertOk();

    expect(UserEntitlement::where('user_id', $recipient->id)->firstOrFail()->isActive())->toBeTrue();
});

test('an unresolvable user id is not guessed at', function () {
    post_event(['app_user_id' => '999999'])->assertOk();

    expect(UserEntitlement::count())->toBe(0);
});

// ── Event ordering ───────────────────────────────────────────────────────────
// Webhooks are not a stream. RevenueCat retries each event on its own schedule,
// so a delivery that failed twice arrives after events that happened later.

test('a stale expiration cannot revoke a purchase that superseded it', function () {
    // The headline case: EXPIRATION for last month's subscription is queued
    // behind two failed deliveries; the customer re-subscribes; the old event
    // finally lands. Last-write-wins leaves a paying customer at tier=free
    // with nothing in the app explaining why.
    $purchasedAt = (int) now()->getPreciseTimestamp(3);

    post_event(['id' => 'evt_buy', 'event_timestamp_ms' => $purchasedAt])->assertOk();

    post_event([
        'id'                 => 'evt_old_expiry',
        'type'               => 'EXPIRATION',
        'event_timestamp_ms' => $purchasedAt - 60_000,
    ])->assertOk();

    $entitlement = UserEntitlement::firstOrFail();

    expect($entitlement->tier->value)->toBe('premium')
        ->and($entitlement->isActive())->toBeTrue()
        ->and($entitlement->last_event_at_ms)->toBe($purchasedAt);
});

test('a stale renewal cannot restore access that was refunded', function () {
    // The same bug pointing the other way, and the quieter of the two: a
    // replayed RENEWAL hands back access somebody was refunded for.
    // Every timestamp here is explicit and derived from the first. Reading the
    // clock twice makes the test pass only when both calls land in the same
    // millisecond — which they usually do, so it fails once a month in CI and
    // looks like a flake rather than the ordering bug it actually is.
    $boughtAt   = (int) now()->getPreciseTimestamp(3);
    $refundedAt = $boughtAt + 60_000;

    post_event(['id' => 'evt_buy2', 'event_timestamp_ms' => $boughtAt])->assertOk();
    post_event(['id' => 'evt_refund', 'type' => 'REFUND', 'event_timestamp_ms' => $refundedAt])->assertOk();

    expect(UserEntitlement::firstOrFail()->isActive())->toBeFalse();

    post_event([
        'id'                 => 'evt_stale_renewal',
        'type'               => 'RENEWAL',
        'event_timestamp_ms' => $refundedAt - 1,
    ])->assertOk();

    expect(UserEntitlement::firstOrFail()->isActive())->toBeFalse();
});

test('two events sharing a millisecond both apply', function () {
    // The comparison is strictly-older, not older-or-equal, and that is a
    // decision rather than an oversight: a transfer emits a pair, ordering
    // cannot separate same-millisecond events anyway, and refusing the second
    // would drop a real change to buy nothing.
    $at = (int) now()->getPreciseTimestamp(3);

    post_event(['id' => 'evt_a', 'event_timestamp_ms' => $at])->assertOk();
    post_event(['id' => 'evt_b', 'type' => 'CANCELLATION', 'event_timestamp_ms' => $at])->assertOk();

    expect(UserEntitlement::firstOrFail()->cancel_at_period_end)->toBeTrue();
});

test('an event carrying no timestamp applies but does not advance the watermark', function () {
    // Unplaceable is not "now". Stamping it now() would silently discard every
    // real event still in flight behind it, which is a worse bug than the one
    // being fixed and harder to see.
    post_event(['id' => 'evt_no_clock', 'event_timestamp_ms' => null])->assertOk();

    $entitlement = UserEntitlement::firstOrFail();

    expect($entitlement->tier->value)->toBe('premium')
        ->and($entitlement->last_event_at_ms)->toBeNull();

    // And an ordinary event behind it still lands, rather than being refused
    // as older than a watermark it never should have set.
    post_event([
        'id'                 => 'evt_after',
        'type'               => 'EXPIRATION',
        'event_timestamp_ms' => (int) now()->subYear()->getPreciseTimestamp(3),
    ])->assertOk();

    expect(UserEntitlement::firstOrFail()->isActive())->toBeFalse();
});

test('a transfer read live from the api is not discarded as stale', function () {
    // The asymmetry that makes the guard correct. receive() does not read the
    // event payload at all — it asks the API what the subscriber owns RIGHT
    // NOW. That answer is current truth, so it is stamped with the read time,
    // not with the timestamp of the event that prompted the read. Order it by
    // the event instead and a delayed TRANSFER silently stops working.
    config()->set('billing.secret_api_key', 'sk_test');

    $recipient = User::factory()->create();

    // The recipient already has a freshly-resolved entitlement, so the
    // watermark is now — well ahead of the transfer event's own clock.
    post_event(['id' => 'evt_recent', 'app_user_id' => (string) $recipient->id])->assertOk();

    Http::fake(['*/subscribers/*' => Http::response([
        'subscriber' => ['entitlements' => [
            'premium' => ['product_identifier' => 'p', 'expires_date' => now()->addYear()->toIso8601String()],
        ]],
    ])]);

    $this->withHeader('Authorization', 'shhh')->postJson('/api/v1/billing/webhook/revenuecat', [
        'event' => [
            'id'                 => 'evt_late_transfer',
            'type'               => 'TRANSFER',
            'environment'        => 'PRODUCTION',
            'event_timestamp_ms' => (int) now()->subDay()->getPreciseTimestamp(3),
            'transferred_to'     => [(string) $recipient->id],
            'transferred_from'   => [],
        ],
    ])->assertOk();

    $entitlement = UserEntitlement::where('user_id', $recipient->id)->firstOrFail();

    // provider=none is what the subscriber-read patch writes, so seeing it
    // proves the API-derived patch landed rather than being refused.
    expect($entitlement->provider->value)->toBe('none')
        ->and($entitlement->isActive())->toBeTrue();
});

test('the ledger records the event clock for diagnosis', function () {
    $at = (int) now()->getPreciseTimestamp(3);

    post_event(['id' => 'evt_stamped', 'event_timestamp_ms' => $at])->assertOk();

    expect(RevenueCatWebhookEvent::firstOrFail()->event_at_ms)->toBe($at);
});
