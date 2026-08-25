<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Onboarding\Models\OnboardingProgress;
use Modules\Onboarding\Support\OnboardingRegistry;

/** Find a step by key. Never assert on a position: the app under test registers its own steps. */
function step(array $data, string $key): array
{
    foreach ($data['steps'] as $candidate) {
        if ($candidate['key'] === $key) {
            return $candidate;
        }
    }

    throw new RuntimeException("Step [{$key}] is not in the response.");
}

beforeEach(function () {
    // last_name empty on purpose: the `profile` step below is satisfied by
    // `filled($user->last_name)`, and the factory fills it. Creating the user
    // the factory's way made five of these tests assert against a step that
    // was already complete before they started — the fixture was wrong, not
    // the code, and it is worth saying so here so nobody "fixes" it back.
    $this->user = User::factory()->unverified()->create(['first_name' => 'Ada', 'last_name' => '']);

    $registry = app(OnboardingRegistry::class);

    $registry->register(
        key: 'profile',
        label: 'Complete your profile',
        description: 'Add a name.',
        route: ['name' => 'profile.edit'],
        icon: 'account_circle',
        required: true,
        completedWhen: fn (User $user) => filled($user->last_name),
        order: 0,
    );

    $registry->register(
        key: 'invite',
        label: 'Invite a colleague',
        required: false,
        order: 10,
    );

    // Optional AND auto-detectable — the combination the precedence rule in
    // OnboardingState exists for. Without a step shaped like this, that rule is
    // unreachable from a test: markCompleted() and markSkipped() each clear the
    // other column, so a row can never carry both.
    $registry->register(
        key: 'avatar',
        label: 'Add a photo',
        required: false,
        completedWhen: fn (User $user) => filled($user->email_verified_at),
        order: 20,
    );
});

test('it reports the declared steps in order with the user position in them', function () {
    $data = $this->actingAs($this->user)->getJson('/api/v1/onboarding')->assertOk()->json('data');

    // Ordering is asserted as a RELATIVE claim between this test's own steps,
    // because the application registers its own alongside them.
    $keys = array_column($data['steps'], 'key');

    expect(array_search('profile', $keys, true))->toBeLessThan(array_search('invite', $keys, true))
        ->and(array_search('invite', $keys, true))->toBeLessThan(array_search('avatar', $keys, true))
        ->and(step($data, 'profile')['required'])->toBeTrue()
        ->and(step($data, 'invite')['required'])->toBeFalse()
        ->and(step($data, 'profile')['completed'])->toBeFalse()
        ->and($data['outstandingRequired'])->toBeGreaterThanOrEqual(1);
});

test('a step already satisfied elsewhere needs no click', function () {
    // The point of completedWhen. Asking someone to come back and tick a box
    // for work they have already done is what makes onboarding feel like
    // paperwork.
    $this->user->update(['last_name' => 'Lovelace']);

    $data = $this->actingAs($this->user)->getJson('/api/v1/onboarding')->assertOk()->json('data');

    expect(step($data, 'profile')['completed'])->toBeTrue()
        ->and(OnboardingProgress::query()->count())->toBe(0, 'nothing should have been written');
});

test('completing a step records it and recomputes the state in one response', function () {
    // `invite`, not `profile`: profile detects itself, and a self-detecting
    // step refuses a manual completion — see the test below.
    $data = $this->actingAs($this->user)
        ->postJson('/api/v1/onboarding/invite/complete')
        ->assertOk()
        ->json('data');

    expect(step($data, 'invite')['completed'])->toBeTrue();

    $this->assertDatabaseHas('onboarding_progress', [
        'user_id'  => $this->user->id,
        'step_key' => 'invite',
    ]);
});

test('a required step cannot be skipped', function () {
    // Otherwise "required" is a label rather than a rule, and the gate passes
    // users who clicked past the thing it exists to insist on.
    $this->actingAs($this->user)
        ->postJson('/api/v1/onboarding/profile/skip')
        ->assertStatus(422);

    expect(step($this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data'), 'profile')['completed'])->toBeFalse();
});

test('an optional step can be skipped and stops being the next step', function () {
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/invite/skip')->assertOk();

    $data = $this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data');

    expect(step($data, 'invite')['skipped'])->toBeTrue()
        ->and($data['nextStep'])->not->toBe('invite');
});

test('doing a skipped step later shows it as done, not passed over', function () {
    // The ordinary path: skip "invite your team" on day one, invite somebody in
    // week two. If the skip stuck, the checklist would keep showing the step as
    // passed-over while the work was done.
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/invite/skip')->assertOk();
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/invite/complete')->assertOk();

    $data = $this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data');

    expect(step($data, 'invite')['completed'])->toBeTrue()
        ->and(step($data, 'invite')['skipped'])->toBeFalse();
});

test('a skipped step that gets satisfied elsewhere reads as done, not passed over', function () {
    // The precedence rule, and the only route to it. A user skips "add a photo"
    // on day one and uploads one from the profile screen in week two — never
    // posting a completion, so the skip row is still there and only
    // `completedWhen` knows the work happened. Reporting that as "skipped"
    // would tell them to go and do something they have already done.
    //
    // Found by mutation-checking: inverting the precedence left every test
    // green, because the earlier skip-then-POST-complete test is satisfied by
    // markCompleted() nulling skipped_at rather than by the rule under test.
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/avatar/skip')->assertOk();

    $before = $this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data');
    expect(step($before, 'avatar')['skipped'])->toBeTrue();

    $this->user->forceFill(['email_verified_at' => now()])->save();

    $after = step($this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data'), 'avatar');

    expect($after['completed'])->toBeTrue()
        ->and($after['skipped'])->toBeFalse();
});

test('skip-all passes over every optional step and leaves required ones alone', function () {
    $data = $this->actingAs($this->user)->postJson('/api/v1/onboarding/skip')->assertOk()->json('data');

    expect(step($data, 'invite')['skipped'])->toBeTrue()
        ->and(step($data, 'profile')['skipped'])->toBeFalse()
        ->and(step($data, 'profile')['completed'])->toBeFalse('skipping everything optional must not satisfy a required step');
});

test('a self-detecting step cannot be ticked off by hand', function () {
    // Otherwise "verify your email" is completed by clicking a button, which is
    // the one thing it must not be. The whole point of `completedWhen` is that
    // the app can see the work; accepting a POST as well makes it a bypass.
    $this->actingAs($this->user)
        ->postJson('/api/v1/onboarding/profile/complete')
        ->assertStatus(422);

    $data = $this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data');

    expect(step($data, 'profile')['completed'])->toBeFalse()
        // And the client is told which steps those are, so it can hide the
        // button rather than offering an action the server will refuse.
        ->and(step($data, 'profile')['autoDetected'])->toBeTrue()
        ->and(step($data, 'invite')['autoDetected'])->toBeFalse();
});

test('an unknown step is a 404 on both verbs', function () {
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/nope/complete')->assertNotFound();
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/nope/skip')->assertNotFound();
});

test('progress is per user', function () {
    $other = User::factory()->unverified()->create(['last_name' => '']);

    $this->actingAs($this->user)->postJson('/api/v1/onboarding/invite/complete')->assertOk();

    expect(step($this->actingAs($other)->getJson('/api/v1/onboarding')->json('data'), 'invite')['completed'])->toBeFalse();
});

test('onboarding requires a signed-in user', function () {
    $this->getJson('/api/v1/onboarding')->assertUnauthorized();
    $this->postJson('/api/v1/onboarding/invite/complete')->assertUnauthorized();
});
