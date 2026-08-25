<?php

declare(strict_types=1);

/**
 * The pre-deploy safety guard, in a file NO option variant drops.
 *
 * These four tests used to live in EntitlementSwitcherTest, which
 * `admin=none` prunes along with the switcher controller. The command itself
 * is not pruned — it is registered unconditionally — so the variant you
 * actually deploy shipped the guard with no coverage at all.
 *
 * That matters more than the usual orphaned test, because of what the guard is
 * for. Its own comment says it: missing keys are silent at runtime, the SDK
 * simply never configures, and every purchase path quietly does nothing. A
 * deploy with REVENUECAT_WEBHOOK_SECRET unset rejects every webhook, grants no
 * entitlement to anyone who pays, and looks completely healthy until the refund
 * requests arrive.
 */
test('the pre-deploy guard fails when the switcher is on for production', function () {
    config()->set('billing.allow_switcher', true);
    config()->set('billing.webhook_secret', 'set');

    $this->artisan('billing:assert-safe --target=production')->assertFailed();
});

test('the pre-deploy guard fails when sandbox granting is on', function () {
    // The single most dangerous item in the build, and nothing turns it off
    // automatically. A human remembering is not a control.
    config()->set('billing.allow_sandbox', true);
    config()->set('billing.allow_switcher', false);
    config()->set('billing.webhook_secret', 'set');

    $this->artisan('billing:assert-safe --target=production')->assertFailed();
});

test('the pre-deploy guard fails when the webhook secret is missing', function () {
    config()->set('billing.allow_sandbox', false);
    config()->set('billing.allow_switcher', false);
    config()->set('billing.webhook_secret', '');

    $this->artisan('billing:assert-safe --target=production')->assertFailed();
});

test('the pre-deploy guard passes on a safe production config', function () {
    config()->set('billing.allow_sandbox', false);
    config()->set('billing.allow_switcher', false);
    config()->set('billing.webhook_secret', 'set');

    $this->artisan('billing:assert-safe --target=production')->assertSuccessful();
});
