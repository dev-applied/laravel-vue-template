<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Billing\Models\UserEntitlement;
use Modules\Billing\Support\Entitlements;

/**
 * The `switcher` variant only. The `none` choice drops this file.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    config()->set('billing.allow_switcher', true);
});

test('the switcher sets the callers own entitlement', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/billing/qa/entitlement', ['tier' => 'premium', 'status' => 'active'])
        ->assertOk();

    expect(app(Entitlements::class)->hasTier($this->user->fresh(), 'premium'))->toBeTrue();
});

test('the switcher is off by default', function () {
    config()->set('billing.allow_switcher', false);

    $this->actingAs($this->user)
        ->postJson('/api/v1/billing/qa/entitlement', ['tier' => 'premium', 'status' => 'active'])
        ->assertNotFound();
});

test('the switcher refuses in production even when the flag is on', function () {
    // The env flag alone is one bad deploy away from being a self-serve
    // upgrade button in production.
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs($this->user)
        ->postJson('/api/v1/billing/qa/entitlement', ['tier' => 'premium', 'status' => 'active'])
        ->assertNotFound();

    expect(UserEntitlement::count())->toBe(0);
});

test('the switcher marks the provider manual', function () {
    // So nothing downstream mistakes a switched state for a real purchase, and
    // management routing does not offer a store link that does not exist.
    $this->actingAs($this->user)
        ->postJson('/api/v1/billing/qa/entitlement', ['tier' => 'premium', 'status' => 'active'])
        ->assertOk();

    expect(UserEntitlement::firstOrFail()->provider->value)->toBe('manual');
});

test('the switcher can reach a trial-expired state', function () {
    // The state that is impractical to reach through real store purchases,
    // which is the whole reason this exists.
    $this->actingAs($this->user)
        ->postJson('/api/v1/billing/qa/entitlement', [
            'tier' => 'free', 'status' => 'lapsed', 'trial_used' => true, 'days_left' => -1,
        ])
        ->assertOk();

    $entitlement = UserEntitlement::firstOrFail();

    expect($entitlement->isActive())->toBeFalse()
        ->and($entitlement->isFirstTime())->toBeFalse();
});

test('the switcher has no privilege over other users', function () {
    $other = User::factory()->create();

    $this->actingAs($this->user)
        ->postJson('/api/v1/billing/qa/entitlement', [
            'tier' => 'premium', 'status' => 'active', 'user_id' => $other->id,
        ])
        ->assertOk();

    expect(UserEntitlement::where('user_id', $other->id)->exists())->toBeFalse();
});

test('the switcher requires authentication', function () {
    $this->postJson('/api/v1/billing/qa/entitlement', ['tier' => 'premium', 'status' => 'active'])
        ->assertUnauthorized();
});
