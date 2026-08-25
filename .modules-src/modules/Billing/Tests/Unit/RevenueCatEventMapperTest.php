<?php

declare(strict_types=1);

use Modules\Billing\Services\MappedEvent;
use Modules\Billing\Services\RevenueCatEventMapper;

/**
 * The mapper is pure — no IO, no clock — so it is unit-testable against
 * captured payloads and can run BEFORE the ledger claim.
 */
beforeEach(fn () => $this->mapper = new RevenueCatEventMapper());

/** Named rcEvent: Laravel already defines a global event() helper. */
function rcEvent(array $overrides = []): array
{
    return array_merge([
        'id'              => 'evt_1',
        'type'            => 'INITIAL_PURCHASE',
        'app_user_id'     => '1',
        'store'           => 'APP_STORE',
        'environment'     => 'PRODUCTION',
        'product_id'      => 'app_premium_monthly',
        'entitlement_ids' => ['premium'],
        'period_type'     => 'NORMAL',
    ], $overrides);
}

test('an initial purchase grants', function () {
    $mapped = $this->mapper->map(rcEvent());

    expect($mapped->kind)->toBe(MappedEvent::KIND_GRANT)
        ->and($mapped->patch['tier'])->toBe('premium')
        ->and($mapped->patch['status'])->toBe('active')
        ->and($mapped->patch['provider'])->toBe('apple');
});

test('the entitlement identifier beats the product sku', function () {
    // Entitlements are what you configured to mean "has premium". SKUs are
    // free-form store strings a client can and will rename.
    $mapped = $this->mapper->map(rcEvent([
        'entitlement_ids' => ['basic'],
        'product_id'      => 'legacy_premium_bundle_v2',
    ]));

    expect($mapped->patch['tier'])->toBe('basic');
});

test('a trial is marked as a trial and burns trial_used', function () {
    $mapped = $this->mapper->map(rcEvent(['period_type' => 'TRIAL']));

    expect($mapped->patch['status'])->toBe('trial')
        ->and($mapped->patch['trial_used'])->toBeTrue();
});

test('every activating type grants', function (string $type) {
    expect($this->mapper->map(rcEvent(['type' => $type]))->kind)->toBe(MappedEvent::KIND_GRANT);
})->with([
    'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE', 'SUBSCRIPTION_EXTENDED',
    'NON_RENEWING_PURCHASE', 'REFUND_REVERSED', 'TEMPORARY_ENTITLEMENT_GRANT',
]);

test('every revoking type revokes completely', function (string $type) {
    $mapped = $this->mapper->map(rcEvent(['type' => $type]));

    // One shared patch on every path that takes access away. A revocation that
    // clears the tier but forgets an expiry leaves a ghost entitlement some
    // other code path will honour.
    expect($mapped->kind)->toBe(MappedEvent::KIND_REVOKE)
        ->and($mapped->patch)->toBe(RevenueCatEventMapper::REVOKED)
        ->and($mapped->patch['current_period_end'])->toBeNull();
})->with(['EXPIRATION', 'REFUND', 'SUBSCRIPTION_PAUSED']);

test('cancellation keeps access and only flags the period end', function () {
    // Cancellation means auto-renew off, NOT loss of access. Downgrading here
    // robs a customer of time they already paid for.
    $mapped = $this->mapper->map(rcEvent(['type' => 'CANCELLATION']));

    expect($mapped->kind)->toBe(MappedEvent::KIND_CANCEL)
        ->and($mapped->patch['cancel_at_period_end'])->toBeTrue()
        ->and($mapped->patch)->not->toHaveKey('tier');
});

test('a billing issue changes nothing', function () {
    // The store runs its own grace period and sends an expiration if it truly
    // lapses. Revoking here cuts off a customer whose card retry is about to
    // succeed.
    $mapped = $this->mapper->map(rcEvent(['type' => 'BILLING_ISSUE']));

    expect($mapped->kind)->toBe(MappedEvent::KIND_INERT)
        ->and($mapped->changesAccess())->toBeFalse();
});

test('a transfer is its own kind, never a grant', function () {
    // It LOOKS like an activation but carries no product, entitlement or
    // expiry — so tier resolution finds nothing, falls through to free, and
    // wipes the account that just received the subscription. This caused a
    // real production demotion.
    $mapped = $this->mapper->map([
        'id'               => 'evt_t',
        'type'             => 'TRANSFER',
        'transferred_to'   => ['2'],
        'transferred_from' => ['1'],
        'environment'      => 'PRODUCTION',
    ]);

    expect($mapped->kind)->toBe(MappedEvent::KIND_TRANSFER)
        ->and($mapped->transferredTo)->toBe(['2'])
        ->and($mapped->transferredFrom)->toBe(['1'])
        ->and($mapped->patch)->toBe([]);
});

test('a transfer is attributed without app_user_id', function () {
    // app_user_id is null on these events, which is exactly why the normal
    // path cannot handle them.
    $mapped = $this->mapper->map([
        'id'             => 'evt_t', 'type' => 'TRANSFER', 'app_user_id' => null,
        'transferred_to' => ['2'], 'transferred_from' => ['1'],
    ]);

    expect($mapped->kind)->toBe(MappedEvent::KIND_TRANSFER);
});

test('an unrecognised type is inert', function () {
    // Vendors add event types over time; a new one must never corrupt a paying
    // customer's state.
    expect($this->mapper->map(rcEvent(['type' => 'SOMETHING_NEW_IN_2027']))->kind)
        ->toBe(MappedEvent::KIND_INERT);
});

test('an event with no user is inert, not an error', function () {
    $mapped = $this->mapper->map(rcEvent(['app_user_id' => null]));

    expect($mapped->kind)->toBe(MappedEvent::KIND_INERT)
        ->and($mapped->reason)->toBe('no attributable user');
});

test('sandbox is detected from the payload', function () {
    expect($this->mapper->map(rcEvent(['environment' => 'SANDBOX']))->isSandbox)->toBeTrue()
        ->and($this->mapper->map(rcEvent())->isSandbox)->toBeFalse();
});

test('the store maps to a provider', function (string $store, string $provider) {
    expect($this->mapper->map(rcEvent(['store' => $store]))->patch['provider'])->toBe($provider);
})->with([
    ['APP_STORE', 'apple'],
    ['PLAY_STORE', 'google'],
    ['RC_BILLING', 'web'],
    ['STRIPE', 'web'],
]);

test('the plan is read from the product identifier', function () {
    expect($this->mapper->map(rcEvent(['product_id' => 'app_premium_annual']))->patch['plan'])->toBe('annual')
        ->and($this->mapper->map(rcEvent(['product_id' => 'app_premium_monthly']))->patch['plan'])->toBe('monthly');
});

test('the mapper touches nothing outside its input', function () {
    // Purity is what lets it run before the ledger claim.
    $payload = rcEvent();
    $before  = $payload;

    $this->mapper->map($payload);

    expect($payload)->toBe($before);
});
