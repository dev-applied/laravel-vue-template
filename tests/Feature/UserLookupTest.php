<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->viewer = User::factory()->create(['first_name' => 'Zed', 'last_name' => 'Zulu', 'email' => 'zed@example.com']);
});

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/users')->assertUnauthorized();
});

test('any signed-in user can list, with no permission needed', function () {
    // The whole reason this stayed in the kernel: an ordinary user filling in
    // an owner picker must not need `manage-users`.
    User::factory()->count(3)->create();

    $response = $this->actingAs($this->viewer)->getJson('/api/v1/users')->assertOk();

    expect($response->json('data'))->toHaveCount(4);
});

test('returns exactly the fields the autocompletes bind to, and nothing else', function () {
    $row = $this->actingAs($this->viewer)->getJson('/api/v1/users')->assertOk()->json('data.0');

    // Asserting the full key list, not just the presence of the three: this is
    // an endpoint every signed-in user can hit, so an accidentally added column
    // is a directory leak.
    expect(array_keys($row))->toBe(['id', 'full_name', 'email'])
        ->and($row['full_name'])->toBe('Zed Zulu');
});

test('searches across first name, last name and email', function () {
    User::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.com']);

    $names = fn (string $q) => collect(
        $this->actingAs($this->viewer)->getJson("/api/v1/users?search={$q}")->assertOk()->json('data')
    )->pluck('full_name')->all();

    expect($names('Grace'))->toBe(['Grace Hopper'])
        ->and($names('Hopper'))->toBe(['Grace Hopper'])
        ->and($names('grace@'))->toBe(['Grace Hopper']);
});

test('caps the result set so one call cannot dump the whole directory', function () {
    User::factory()->count(60)->create();

    expect($this->actingAs($this->viewer)->getJson('/api/v1/users')->assertOk()->json('data'))
        ->toHaveCount(50);
});

test('hides deactivated users once the Users module has added the column', function () {
    // Guarded on the column existing, because the kernel table has no such
    // column until modules/Users is installed and migrated.
    if (! Schema::hasColumn('users', 'deactivated_at')) {
        $this->markTestSkipped('modules/Users not installed — no deactivated_at column.');
    }

    $gone                 = User::factory()->create(['first_name' => 'Gone', 'last_name' => 'Away']);
    $gone->deactivated_at = now();
    $gone->save();

    $names = collect($this->actingAs($this->viewer)->getJson('/api/v1/users')->assertOk()->json('data'))
        ->pluck('full_name');

    expect($names)->not->toContain('Gone Away')
        ->and($names)->toContain('Zed Zulu');
});

test('a search does not let the deactivation filter escape via the OR group', function () {
    if (! Schema::hasColumn('users', 'deactivated_at')) {
        $this->markTestSkipped('modules/Users not installed — no deactivated_at column.');
    }

    // The bug this pins: chaining ->where()->orWhere() ungrouped alongside the
    // whereNull makes the OR swallow it, so searching surfaces deactivated
    // accounts that the unfiltered list correctly hides.
    $gone                 = User::factory()->create(['first_name' => 'Hidden', 'last_name' => 'Person', 'email' => 'hidden@example.com']);
    $gone->deactivated_at = now();
    $gone->save();

    $data = $this->actingAs($this->viewer)->getJson('/api/v1/users?search=Hidden')->assertOk()->json('data');

    expect($data)->toBe([]);
});
