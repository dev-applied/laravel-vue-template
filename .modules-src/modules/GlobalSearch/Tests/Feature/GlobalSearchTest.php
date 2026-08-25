<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\GlobalSearch\Support\SearchRegistry;

beforeEach(function () {
    $this->user = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    // Registered against User — the only model the kernel contract guarantees
    // a module can rely on.
    app(SearchRegistry::class)->register(
        key: 'users',
        label: 'Users',
        query: fn (string $term) => User::query()
            ->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"))
            ->orderBy('id'),
        title: fn (User $user) => "{$user->first_name} {$user->last_name}",
        subtitle: fn (User $user) => $user->email,
        route: fn (User $user) => ['name' => 'users.show', 'params' => ['id' => $user->id]],
        icon: 'person',
    );
});

test('it groups matches by source and presents them through the source closures', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/search?q=Lovelace')
        ->assertOk();

    $group = collect($response->json('data.groups'))->firstWhere('type', 'users');

    expect($group)->not->toBeNull('the registered source returned no group')
        ->and($group['label'])->toBe('Users')
        ->and($group['results'][0]['title'])->toBe('Ada Lovelace')
        ->and($group['results'][0]['subtitle'])->toBe($this->user->email)
        ->and($group['results'][0]['route'])->toBe(['name' => 'users.show', 'params' => ['id' => $this->user->id]])
        ->and($group['results'][0]['icon'])->toBe('person');
});

test('a source that matches nothing is omitted rather than returned empty', function () {
    $groups = $this->actingAs($this->user)
        ->getJson('/api/v1/search?q=Hopper')
        ->assertOk()
        ->json('data.groups');

    expect(collect($groups)->firstWhere('type', 'users'))->toBeNull()
        ->and($this->actingAs($this->user)->getJson('/api/v1/search?q=Hopper')->json('data.total'))->toBe(0);
});

test('an unauthorised source is invisible, not empty', function () {
    // "Empty" and "you may not see this" are different statements, and the
    // second one is not the caller's to have. If an unauthorised source were
    // returned with zero results, anyone could enumerate which types exist.
    Gate::define('search-secrets', fn () => false);

    app(SearchRegistry::class)->register(
        key: 'secrets',
        label: 'Secrets',
        query: fn (string $term) => User::query(),
        title: fn (User $user) => 'classified',
        ability: 'search-secrets',
    );

    $body = $this->actingAs($this->user)->getJson('/api/v1/search?q=Lovelace')->assertOk()->json();

    expect(collect($body['data']['groups'])->pluck('type'))->not->toContain('secrets');

    // …and the types endpoint agrees, which is what the palette's filter chips
    // are built from.
    $types = $this->actingAs($this->user)->getJson('/api/v1/search/types')->assertOk()->json('data.*.type');

    expect($types)->toContain('users')->not->toContain('secrets');
});

test('types= narrows to the named sources', function () {
    app(SearchRegistry::class)->register(
        key: 'admins',
        label: 'Admins',
        query: fn (string $term) => User::query()->where('last_name', 'like', "%{$term}%"),
        title: fn (User $user) => $user->last_name,
    );

    $groups = $this->actingAs($this->user)
        ->getJson('/api/v1/search?q=Lovelace&types[]=admins')
        ->assertOk()
        ->json('data.groups');

    expect(collect($groups)->pluck('type')->all())->toBe(['admins']);
});

test('an unregistered type is rejected rather than ignored', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/search?q=Lovelace&types[]=nope')
        ->assertStatus(422)
        ->assertJsonValidationErrors('types.0');
});

test('one character is refused', function () {
    // A single character matches most of every table at once — a scan per
    // source for a result nobody can use.
    $this->actingAs($this->user)->getJson('/api/v1/search?q=a')->assertStatus(422);
    $this->actingAs($this->user)->getJson('/api/v1/search?q=ad')->assertOk();
});

test('hasMore is reported without a second count query', function () {
    User::factory()->count(7)->create(['last_name' => 'Lovelace']);

    $group = collect(
        $this->actingAs($this->user)->getJson('/api/v1/search?q=Lovelace&limit=3')->assertOk()->json('data.groups')
    )->firstWhere('type', 'users');

    expect($group['results'])->toHaveCount(3)
        ->and($group['hasMore'])->toBeTrue();

    $exact = collect(
        $this->actingAs($this->user)->getJson('/api/v1/search?q=Lovelace&limit=25')->assertOk()->json('data.groups')
    )->firstWhere('type', 'users');

    expect($exact['results'])->toHaveCount(8)
        ->and($exact['hasMore'])->toBeFalse();
});

test('search requires a signed-in user', function () {
    $this->getJson('/api/v1/search?q=Lovelace')->assertUnauthorized();
});

test('the registry sorts by declared order then label', function () {
    $registry = app(SearchRegistry::class);

    $registry->register(key: 'zebras', label: 'Zebras', query: fn ($t) => User::query(), title: fn ($u) => 'z', order: -5);
    $registry->register(key: 'apples', label: 'Apples', query: fn ($t) => User::query(), title: fn ($u) => 'a', order: 10);

    $keys = array_keys($registry->authorisedFor($this->user));

    expect(array_search('zebras', $keys, true))->toBeLessThan(array_search('users', $keys, true))
        ->and(array_search('apples', $keys, true))->toBeGreaterThan(array_search('users', $keys, true));
});
