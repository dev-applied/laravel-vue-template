<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Asserted over HTTP rather than by calling toArray() directly: the optional
 * keys are MissingValue until a response serializes them away, so a direct call
 * reports keys no client ever receives.
 */
function auth_user_payload(TestCase $test, User $owner): array
{
    $item   = Item::factory()->create(['owner_id' => $owner->id]);
    $viewer = User::factory()->create();
    $token  = $viewer->createToken('test')->plainTextToken;

    return $test->getJson("/api/v1/items/{$item->id}", ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->json('data.owner');
}

test('the payload is an allow-list, so a new users column cannot leak by default', function () {
    // The point of the test: adding a column to `users` — which any module may
    // do — must not silently widen what every client receives.
    $keys = array_keys(auth_user_payload($this, User::factory()->create()));

    expect($keys)->toBe(['id', 'first_name', 'last_name', 'full_name', 'email']);
});

test('never exposes credentials', function () {
    $payload = auth_user_payload($this, User::factory()->create());

    expect($payload)->not->toHaveKey('password')
        ->and($payload)->not->toHaveKey('remember_token');
});

test('carries full_name, which every user autocomplete renders', function () {
    $payload = auth_user_payload($this, User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']));

    expect($payload['full_name'])->toBe('Ada Lovelace');
});

test('an item owner is serialized through the same allow-list', function () {
    $owner = User::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    $item  = Item::factory()->create(['owner_id' => $owner->id]);

    $viewer = User::factory()->create();
    $token  = $viewer->createToken('test')->plainTextToken;

    $payload = $this->getJson("/api/v1/items/{$item->id}", ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->json('data.owner');

    expect(array_keys($payload))->toBe(['id', 'first_name', 'last_name', 'full_name', 'email']);
});

test('serializing a user issues no extra queries for roles or permissions', function () {
    // The regression this guards: `isset($user->roles)` reads as a harmless
    // presence check but routes through getAttribute(), which LAZY-LOADS the
    // relation — one query per serialized user, so an owner column on a
    // 25-row table becomes 25 queries.
    $owner  = User::factory()->create();
    $item   = Item::factory()->create(['owner_id' => $owner->id]);
    $viewer = User::factory()->create();
    $token  = $viewer->createToken('test')->plainTextToken;

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->getJson("/api/v1/items/{$item->id}", ['Authorization' => "Bearer {$token}"])->assertOk();

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $q) => str_contains($q, 'role') || str_contains($q, 'permission')))
        ->toBeEmpty();
});
