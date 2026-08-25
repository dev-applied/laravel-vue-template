<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Modules\Billing\Models\UserEntitlement;
use Modules\Billing\Support\Entitlements;

beforeEach(function () {
    $this->user = User::factory()->create();

    Route::middleware(['api', 'auth:sanctum', 'tier:premium'])
        ->get('/api/v1/_test/premium', fn () => response()->json(['ok' => true]));
});

test('a free user is refused with 402, not 403', function () {
    // 402 so the frontend opens the paywall, rather than treating it as a
    // permissions problem or logging the person out.
    $this->actingAs($this->user)
        ->getJson('/api/v1/_test/premium')
        ->assertStatus(402)
        ->assertJsonPath('upgrade', true)
        ->assertJsonPath('requiredTier', 'premium');
});

test('a subscriber is let through', function () {
    UserEntitlement::create([
        'user_id'            => $this->user->id, 'tier' => 'premium', 'status' => 'active',
        'current_period_end' => now()->addMonth(),
    ]);

    $this->actingAs($this->user->fresh())->getJson('/api/v1/_test/premium')->assertOk();
});

test('a lower tier does not satisfy a higher one', function () {
    UserEntitlement::create([
        'user_id'            => $this->user->id, 'tier' => 'basic', 'status' => 'active',
        'current_period_end' => now()->addMonth(),
    ]);

    $this->actingAs($this->user->fresh())->getJson('/api/v1/_test/premium')->assertStatus(402);
});

test('a higher tier satisfies a lower one', function () {
    Route::middleware(['api', 'auth:sanctum', 'tier:basic'])
        ->get('/api/v1/_test/basic', fn () => response()->json(['ok' => true]));

    UserEntitlement::create([
        'user_id'            => $this->user->id, 'tier' => 'premium', 'status' => 'active',
        'current_period_end' => now()->addMonth(),
    ]);

    $this->actingAs($this->user->fresh())->getJson('/api/v1/_test/basic')->assertOk();
});

test('a cancelled subscription keeps access until the period ends', function () {
    UserEntitlement::create([
        'user_id'              => $this->user->id, 'tier' => 'premium', 'status' => 'cancelled',
        'cancel_at_period_end' => true, 'current_period_end' => now()->addWeek(),
    ]);

    $this->actingAs($this->user->fresh())->getJson('/api/v1/_test/premium')->assertOk();
});

test('an expired period stops granting even with no expiration webhook', function () {
    // A webhook can be missed. The clock is the backstop.
    UserEntitlement::create([
        'user_id'            => $this->user->id, 'tier' => 'premium', 'status' => 'active',
        'current_period_end' => now()->subDay(),
    ]);

    $this->actingAs($this->user->fresh())->getJson('/api/v1/_test/premium')->assertStatus(402);
});

test('a user with no entitlement row is a free user, not an error', function () {
    // Resolved through the service, so the module works before anyone adds the
    // optional HasEntitlement trait to the kernel's User model.
    $entitlements = app(Entitlements::class);

    expect($entitlements->isActive($this->user))->toBeFalse()
        ->and($entitlements->for($this->user)->tier->value)->toBe('free');
});

test('the read endpoint distinguishes never-subscribed from trial-expired', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/billing/entitlement')->assertOk();

    expect($response->json('isFirstTime'))->toBeTrue();

    UserEntitlement::create([
        'user_id' => $this->user->id, 'tier' => 'free', 'status' => 'lapsed', 'trial_used' => true,
    ]);

    expect($this->actingAs($this->user->fresh())->getJson('/api/v1/billing/entitlement')->json('isFirstTime'))
        ->toBeFalse();
});

test('management routing follows the processor, not the device', function () {
    UserEntitlement::create([
        'user_id'  => $this->user->id, 'tier' => 'premium', 'status' => 'active',
        'provider' => 'apple', 'current_period_end' => now()->addMonth(),
    ]);

    $url = $this->actingAs($this->user->fresh())->getJson('/api/v1/billing/entitlement')->json('managementUrl');

    expect($url)->toContain('apple.com');
});

test('the entitlement read requires authentication', function () {
    $this->getJson('/api/v1/billing/entitlement')->assertUnauthorized();
});
