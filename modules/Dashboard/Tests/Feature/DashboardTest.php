<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Dashboard\Support\DashboardRegistry;

beforeEach(function () {
    $this->user     = User::factory()->create();
    $this->registry = app(DashboardRegistry::class);
});

test('registered widgets come back in one batched response', function () {
    $this->registry->stat('a', 'Widget A', fn () => ['value' => 1]);
    $this->registry->queue('b', 'Widget B', fn () => ['items' => []]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonCount(2, 'widgets')
        ->assertJsonPath('widgets.0.key', 'a')
        ->assertJsonPath('widgets.0.type', 'stat')
        ->assertJsonPath('widgets.0.data.value', 1);
});

test('widgets are ordered by their declared order then key', function () {
    $this->registry->stat('z', 'Z', fn () => [], order: 10);
    $this->registry->stat('a', 'A', fn () => [], order: 20);

    $keys = $this->actingAs($this->user)->getJson('/api/v1/dashboard')->json('widgets.*.key');

    expect($keys)->toBe(['z', 'a']);
});

test('the resolver receives the viewing user so a tile can be scoped', function () {
    $this->registry->stat('mine', 'Mine', fn ($user) => ['value' => $user->id]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard')
        ->assertJsonPath('widgets.0.data.value', $this->user->id);
});

test('a widget the viewer may not see is omitted entirely', function () {
    // Omitted, not returned empty — an empty tile still leaks that it exists.
    Gate::define('see-secret', fn () => false);
    $this->registry->stat('secret', 'Secret', fn () => ['value' => 42], ability: 'see-secret');
    $this->registry->stat('public', 'Public', fn () => ['value' => 1]);

    $keys = $this->actingAs($this->user)->getJson('/api/v1/dashboard')->json('widgets.*.key');

    expect($keys)->toBe(['public']);
});

test('a permitted gated widget is included', function () {
    Gate::define('see-secret', fn () => true);
    $this->registry->stat('secret', 'Secret', fn () => ['value' => 42], ability: 'see-secret');

    $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard')
        ->assertJsonPath('widgets.0.data.value', 42);
});

test('one failing widget does not blank the dashboard', function () {
    $this->registry->stat('broken', 'Broken', fn () => throw new RuntimeException('boom'));
    $this->registry->stat('fine', 'Fine', fn () => ['value' => 7]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard')->assertOk();

    expect($response->json('widgets.0.error'))->not->toBeNull()
        ->and($response->json('widgets.0.data'))->toBeNull()
        ->and($response->json('widgets.1.data.value'))->toBe(7);
});

test('a failing widget never leaks the exception message to the client', function () {
    $this->registry->stat('broken', 'Broken', fn () => throw new RuntimeException('connection string leaked'));

    $error = $this->actingAs($this->user)->getJson('/api/v1/dashboard')->json('widgets.0.error');

    expect($error)->not->toContain('connection string leaked');
});

test('the only parameter narrows what is resolved', function () {
    $resolved = [];
    $this->registry->stat('a', 'A', function () use (&$resolved) {
        $resolved[] = 'a';

        return [];
    });
    $this->registry->stat('b', 'B', function () use (&$resolved) {
        $resolved[] = 'b';

        return [];
    });

    $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard?only[]=b')
        ->assertOk()
        ->assertJsonCount(1, 'widgets');

    // The skipped widget's resolver must not run — that is the point of `only`.
    expect($resolved)->toBe(['b']);
});

test('a cached widget is scoped per user', function () {
    // A scoped tile cached globally would show one person another's numbers.
    $this->registry->stat('mine', 'Mine', fn ($user) => ['value' => $user->id], cacheSeconds: 60);

    $other = User::factory()->create();

    $first  = $this->actingAs($this->user)->getJson('/api/v1/dashboard')->json('widgets.0.data.value');
    $second = $this->actingAs($other)->getJson('/api/v1/dashboard')->json('widgets.0.data.value');

    expect($first)->toBe($this->user->id)
        ->and($second)->toBe($other->id);
});

test('an empty registry returns an empty list rather than failing', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('widgets', []);
});

test('the dashboard requires authentication', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});
