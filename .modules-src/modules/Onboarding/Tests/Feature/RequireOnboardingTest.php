<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Modules\Onboarding\Support\OnboardingRegistry;

/**
 * Satisfy every required step the API reports, not just this test's own.
 *
 * The registry is shared with the application under test, which registers its
 * own steps from a service provider — so "the gate opens" cannot be arranged by
 * completing one step. Auto-detected steps refuse a manual POST by design, so
 * the common ones are satisfied for real: verifying the user's email covers the
 * usual "verify your email" step. Anything still outstanding after that is a
 * step this test cannot satisfy generically, and it says so rather than
 * pretending to have passed.
 */
function satisfyAllRequired(Tests\TestCase $test, $user): void
{
    $user->forceFill(['email_verified_at' => now()])->save();

    foreach ($test->actingAs($user)->getJson('/api/v1/onboarding')->json('data.steps') as $step) {
        if ($step['required'] && ! $step['completed'] && ! $step['autoDetected']) {
            $test->actingAs($user)->postJson("/api/v1/onboarding/{$step['key']}/complete")->assertOk();
        }
    }

    $state = $test->actingAs($user)->getJson('/api/v1/onboarding')->json('data');

    if ($state['outstandingRequired'] === 0) {
        return;
    }

    // Something is still outstanding. WHY decides whether skipping is honest.
    //
    // A required step this helper cannot satisfy generically (auto-detected,
    // and not the email one) is a genuine environment limitation — skip.
    // Anything else means the count is wrong, and skipping there hides the bug:
    // mutating `outstandingRequired` to include OPTIONAL steps turned this
    // helper's skip into a green run, which is the same failure as a lint check
    // that reports ok when it did not check anything.
    $unsatisfiable = collect($state['steps'])
        ->filter(fn (array $step) => $step['required'] && ! $step['completed'] && $step['autoDetected'])
        ->count();

    if ($unsatisfiable === $state['outstandingRequired']) {
        $test->markTestSkipped("{$unsatisfiable} required step(s) cannot be satisfied generically from this test.");
    }

    throw new RuntimeException(
        "outstandingRequired is {$state['outstandingRequired']} but only {$unsatisfiable} required step(s) are "
        .'unsatisfiable — the count is including something it should not.'
    );
}

beforeEach(function () {
    $this->user = User::factory()->unverified()->create();

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
        ->and($response->json('onboarding.nextStep'))->toBeString()
        ->and($response->json('onboarding.outstandingRequired'))->toBeGreaterThanOrEqual(1);
});

test('the gate opens once the required steps are done', function () {
    satisfyAllRequired($this, $this->user);

    $this->actingAs($this->user)->getJson('/api/v1/_test/gated')->assertOk()->assertJson(['ok' => true]);
});

test('an outstanding OPTIONAL step does not hold the gate shut', function () {
    // Which is the whole meaning of optional. A gate that blocks on them makes
    // every step required by another name.
    satisfyAllRequired($this, $this->user);

    $tour = collect($this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data.steps'))
        ->firstWhere('key', 'tour');

    expect($tour['completed'])->toBeFalse('the optional step must still be outstanding for this to prove anything');

    $this->actingAs($this->user)->getJson('/api/v1/_test/gated')->assertOk();
});

test('the onboarding endpoints themselves are never gated', function () {
    // The failure this prevents is a signed-in user who can reach nothing at
    // all, including the screen that would release them.
    $this->actingAs($this->user)->getJson('/api/v1/onboarding')->assertOk();
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/tour/skip')->assertOk();
});

test('the gate defers to auth rather than answering for it', function () {
    // Middleware order puts auth first, but if a project ever applies
    // `onboarded` alone the right answer is still 401, not 403 — "who are you"
    // is a different question from "have you finished setting up".
    $this->getJson('/api/v1/_test/gated')->assertUnauthorized();
});
