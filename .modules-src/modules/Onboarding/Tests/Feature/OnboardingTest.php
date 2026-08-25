<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Onboarding\Models\OnboardingProgress;
use Modules\Onboarding\Support\OnboardingRegistry;

beforeEach(function () {
    $this->user = User::factory()->create(['first_name' => 'Ada']);

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
});

test('it reports the declared steps in order with the user position in them', function () {
    $data = $this->actingAs($this->user)->getJson('/api/v1/onboarding')->assertOk()->json('data');

    expect(array_column($data['steps'], 'key'))->toBe(['profile', 'invite'])
        ->and($data['steps'][0]['required'])->toBeTrue()
        ->and($data['steps'][1]['required'])->toBeFalse()
        ->and($data['nextStep'])->toBe('profile')
        ->and($data['complete'])->toBeFalse()
        ->and($data['outstandingRequired'])->toBe(1);
});

test('a step already satisfied elsewhere needs no click', function () {
    // The point of completedWhen. Asking someone to come back and tick a box
    // for work they have already done is what makes onboarding feel like
    // paperwork.
    $this->user->update(['last_name' => 'Lovelace']);

    $data = $this->actingAs($this->user)->getJson('/api/v1/onboarding')->assertOk()->json('data');

    expect($data['steps'][0]['completed'])->toBeTrue()
        ->and($data['complete'])->toBeTrue()
        ->and($data['nextStep'])->toBe('invite')
        ->and(OnboardingProgress::query()->count())->toBe(0, 'nothing should have been written');
});

test('completing a step records it and recomputes the state in one response', function () {
    $data = $this->actingAs($this->user)
        ->postJson('/api/v1/onboarding/profile/complete')
        ->assertOk()
        ->json('data');

    expect($data['steps'][0]['completed'])->toBeTrue()
        ->and($data['complete'])->toBeTrue()
        ->and($data['completedCount'])->toBe(1);

    $this->assertDatabaseHas('onboarding_progress', [
        'user_id' => $this->user->id,
        'step_key' => 'profile',
    ]);
});

test('a required step cannot be skipped', function () {
    // Otherwise "required" is a label rather than a rule, and the gate passes
    // users who clicked past the thing it exists to insist on.
    $this->actingAs($this->user)
        ->postJson('/api/v1/onboarding/profile/skip')
        ->assertStatus(422);

    expect($this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data.complete'))->toBeFalse();
});

test('an optional step can be skipped and stops being the next step', function () {
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/invite/skip')->assertOk();

    $data = $this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data');

    expect($data['steps'][1]['skipped'])->toBeTrue()
        ->and($data['nextStep'])->toBe('profile');
});

test('doing a skipped step later shows it as done, not passed over', function () {
    // The ordinary path: skip "invite your team" on day one, invite somebody in
    // week two. If the skip stuck, the checklist would keep showing the step as
    // passed-over while the work was done.
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/invite/skip')->assertOk();
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/invite/complete')->assertOk();

    $data = $this->actingAs($this->user)->getJson('/api/v1/onboarding')->json('data');

    expect($data['steps'][1]['completed'])->toBeTrue()
        ->and($data['steps'][1]['skipped'])->toBeFalse();
});

test('skip-all passes over every optional step and leaves required ones alone', function () {
    $data = $this->actingAs($this->user)->postJson('/api/v1/onboarding/skip')->assertOk()->json('data');

    expect($data['steps'][1]['skipped'])->toBeTrue()
        ->and($data['steps'][0]['skipped'])->toBeFalse()
        ->and($data['complete'])->toBeFalse('skipping everything optional must not satisfy a required step');
});

test('an unknown step is a 404 on both verbs', function () {
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/nope/complete')->assertNotFound();
    $this->actingAs($this->user)->postJson('/api/v1/onboarding/nope/skip')->assertNotFound();
});

test('progress is per user', function () {
    $other = User::factory()->create();

    $this->actingAs($this->user)->postJson('/api/v1/onboarding/profile/complete')->assertOk();

    expect($this->actingAs($other)->getJson('/api/v1/onboarding')->json('data.steps.0.completed'))->toBeFalse();
});

test('onboarding requires a signed-in user', function () {
    $this->getJson('/api/v1/onboarding')->assertUnauthorized();
    $this->postJson('/api/v1/onboarding/profile/complete')->assertUnauthorized();
});
