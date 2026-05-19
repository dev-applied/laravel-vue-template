<?php

declare(strict_types=1);

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\User;

beforeEach(function () {
    $this->user  = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->auth  = ['Authorization' => "Bearer {$this->token}"];
});

test('store creates an item with valid data', function () {
    $owner = User::factory()->create();

    $payload = [
        'name'        => 'New thing',
        'description' => 'Some details',
        'status'      => ItemStatus::Active->value,
        'priority'    => 4,
        'due_date'    => now()->addDays(7)->toDateString(),
        'owner_id'    => $owner->id,
    ];

    $response = $this->postJson('/api/v1/items', $payload, $this->auth);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'New thing')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.priority', 4)
        ->assertJsonPath('data.owner.id', $owner->id);

    $this->assertDatabaseHas('items', [
        'name'          => 'New thing',
        'created_by_id' => $this->user->id,
        'updated_by_id' => $this->user->id,
    ]);
});

test('store validates required fields', function () {
    $response = $this->postJson('/api/v1/items', [], $this->auth);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'status', 'priority']);
});

test('store rejects unknown status', function () {
    $response = $this->postJson('/api/v1/items', [
        'name'     => 'X',
        'status'   => 'nonsense',
        'priority' => 1,
    ], $this->auth);

    $response->assertUnprocessable()->assertJsonValidationErrors('status');
});

test('update accepts partial payload', function () {
    $item = Item::factory()->ownedBy($this->user->id)->create(['name' => 'Original', 'priority' => 1]);

    $response = $this->patchJson("/api/v1/items/{$item->id}", [
        'priority' => 5,
    ], $this->auth);

    $response->assertOk()
        ->assertJsonPath('data.priority', 5)
        ->assertJsonPath('data.name', 'Original');

    $this->assertDatabaseHas('items', [
        'id'            => $item->id,
        'priority'      => 5,
        'updated_by_id' => $this->user->id,
    ]);
});

test('update validates rules on provided fields', function () {
    $item = Item::factory()->ownedBy($this->user->id)->create();

    $this->patchJson("/api/v1/items/{$item->id}", [
        'priority' => 99,
    ], $this->auth)->assertUnprocessable()->assertJsonValidationErrors('priority');
});

test('update is forbidden for non-owner', function () {
    $other = User::factory()->create();
    $item  = Item::factory()->ownedBy($other->id)->create(['priority' => 1]);

    $this->patchJson("/api/v1/items/{$item->id}", ['priority' => 5], $this->auth)
        ->assertForbidden();

    $this->assertDatabaseHas('items', ['id' => $item->id, 'priority' => 1]);
});

test('destroy soft-deletes the item', function () {
    $item = Item::factory()->ownedBy($this->user->id)->create();

    $this->deleteJson("/api/v1/items/{$item->id}", [], $this->auth)
        ->assertNoContent();

    $this->assertSoftDeleted('items', ['id' => $item->id]);
});

test('destroy is forbidden for non-owner', function () {
    $other = User::factory()->create();
    $item  = Item::factory()->ownedBy($other->id)->create();

    $this->deleteJson("/api/v1/items/{$item->id}", [], $this->auth)
        ->assertForbidden();

    $this->assertDatabaseHas('items', ['id' => $item->id, 'deleted_at' => null]);
});
