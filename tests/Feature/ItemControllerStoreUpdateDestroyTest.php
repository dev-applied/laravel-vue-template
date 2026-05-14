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
        ->assertJsonPath('name', 'New thing')
        ->assertJsonPath('status', 'active')
        ->assertJsonPath('priority', 4)
        ->assertJsonPath('owner.id', $owner->id);

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
    $item = Item::factory()->create(['name' => 'Original', 'priority' => 1]);

    $response = $this->patchJson("/api/v1/items/{$item->id}", [
        'priority' => 5,
    ], $this->auth);

    $response->assertOk()
        ->assertJsonPath('priority', 5)
        ->assertJsonPath('name', 'Original');

    $this->assertDatabaseHas('items', [
        'id'            => $item->id,
        'priority'      => 5,
        'updated_by_id' => $this->user->id,
    ]);
});

test('update validates rules on provided fields', function () {
    $item = Item::factory()->create();

    $this->patchJson("/api/v1/items/{$item->id}", [
        'priority' => 99,
    ], $this->auth)->assertUnprocessable()->assertJsonValidationErrors('priority');
});

test('destroy soft-deletes the item', function () {
    $item = Item::factory()->create();

    $this->deleteJson("/api/v1/items/{$item->id}", [], $this->auth)
        ->assertNoContent();

    $this->assertSoftDeleted('items', ['id' => $item->id]);
});
