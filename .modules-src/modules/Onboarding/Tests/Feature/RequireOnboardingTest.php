<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Modules\Onboarding\Support\OnboardingRegistry;

beforeEach(function () {
    $this->user = User::factory()->create();

    app(OnboardingRegistry::class)->register(
        key: 'profile',
        label: 'Complete your profile',
        required: true,
    );

    app(OnboardingRegistry::class)->register(
        key: 'tour',
        label: 'Take the tour',
        required: false,
    );

    Route::middleware(['auth:sanctum', 'onboarded'])
        ->get('/api/v1/_test/gated', fn () => response()->json(['ok' => true]));
});

test('a gated route is refused while a required step is outstanding', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/_test/gated')->assertForbidden();

    // A machine-readable body rather than a redirect: this is an API and the
    // SPA router decides where to send someone. The next step is named so the
    // client can go straight there instead of re-fetching to find out.
    expect($response->json('onboarding.complete'))->toBeFalse()
        ->and($response->json('onboarding.nextStep'))->toBe('profile')
        ->and($response->json('onboarding.outstandingRequired'))->toBe(1);
});

test('the gate opens once the required steps are done', function () {
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/profile/complete')->assertOk();

    $this->actingAs($this->user)->getJson('/api/v1/_test/gated')->assertOk()->assertJson(['ok' => true]);
});

test('an outstanding OPTIONAL step does not hold the gate shut', function () {
    // Which is the whole meaning of optional. A gate that blocks on them makes
    // every step required by another name.
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/profile/complete')->assertOk();

    expect($this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data.steps.1.completed'))->toBeFalse();

    $this->actingAs($this->user)->getJson('/api/v1/_test/gated')->assertOk();
});

test('the onboarding endpoints themselves are never gated', function () {
    // The failure this prevents is a signed-in user who can reach nothing at
    // all, including the screen that would release them.
    $this->actingAs($this->user)->getJson('/api/v1/onboarding')->assertOk();
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/profile/complete')->assertOk();
});

test('the gate defers to auth rather than answering for it', function () {
    // Middleware order puts auth first, but if a project ever applies
    // `onboarded` alone the right answer is still 401, not 403 — "who are you"
    // is a different question from "have you finished setting up".
    $this->getJson('/api/v1/_test/gated')->assertUnauthorized();
});
