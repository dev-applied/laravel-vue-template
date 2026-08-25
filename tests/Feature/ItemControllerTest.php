<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;

beforeEach(function () {
    $this->user  = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->auth  = ['Authorization' => "Bearer {$this->token}"];
});

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/items')->assertUnauthorized();
});

test('index returns paginated items', function () {
    Item::factory()->count(15)->create();

    $response = $this->getJson('/api/v1/items', $this->auth);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'status', 'priority', 'due_date', 'owner']],
            'total',
            'per_page',
            'current_page',
            'last_page',
        ])
        ->assertJsonPath('total', 15);
});

test('index filters by status', function () {
    Item::factory()->active()->count(3)->create();
    Item::factory()->archived()->count(2)->create();

    $response = $this->getJson('/api/v1/items?status=active', $this->auth);

    $response->assertOk()->assertJsonPath('total', 3);
});

test('index filters by owner_id', function () {
    $other = User::factory()->create();
    Item::factory()->ownedBy($this->user->id)->count(4)->create();
    Item::factory()->ownedBy($other->id)->count(2)->create();

    $response = $this->getJson("/api/v1/items?owner_id={$this->user->id}", $this->auth);

    $response->assertOk()->assertJsonPath('total', 4);
});

test('index searches name and description', function () {
    Item::factory()->create(['name' => 'Unique haystack alpha']);
    Item::factory()->create(['description' => 'this contains haystack inside']);
    Item::factory()->create(['name' => 'unrelated']);

    $response = $this->getJson('/api/v1/items?search=haystack', $this->auth);

    $response->assertOk()->assertJsonPath('total', 2);
});

test('show returns a single item with owner relation loaded', function () {
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    $item  = Item::factory()->ownedBy($owner->id)->create(['name' => 'Sample']);

    $response = $this->getJson("/api/v1/items/{$item->id}", $this->auth);

    // JsonResource wraps single-record responses in `data` by default.
    $response->assertOk()
        ->assertJsonPath('data.name', 'Sample')
        ->assertJsonPath('data.owner.email', 'owner@example.test');
});

test('show 404s for missing item', function () {
    $this->getJson('/api/v1/items/99999', $this->auth)->assertNotFound();
});

test('show wraps the item in a data envelope', function () {
    // Pinned because the frontend edit page reads `response.data.data`. When the
    // controller returns an ItemResource directly, Laravel wraps it — dropping
    // that envelope (e.g. by switching to response()->json($resource)) would
    // silently blank every field on the edit form rather than error.
    $item = Item::factory()->create(['name' => 'Enveloped']);

    $this->getJson("/api/v1/items/{$item->id}", $this->auth)
        ->assertOk()
        ->assertJsonPath('data.id', $item->id)
        ->assertJsonPath('data.name', 'Enveloped');
});
