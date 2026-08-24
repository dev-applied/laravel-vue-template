<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Example\Models\ExampleNote;

test('example notes index returns module data', function () {
    ExampleNote::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/example-notes')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('example note can be created through the module route', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/example-notes', ['note' => 'From the module test'])
        ->assertCreated()
        ->assertJsonPath('data.note', 'From the module test');

    expect(ExampleNote::query()->count())->toBe(1);
});

test('example notes require authentication', function () {
    $this->getJson('/api/v1/example-notes')->assertUnauthorized();
});

test('example note validation rejects a missing note', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/example-notes', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['note']);
});
