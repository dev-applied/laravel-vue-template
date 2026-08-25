<?php

declare(strict_types=1);

use App\Models\User;
use Modules\GlobalSearch\Models\SearchHistory;

beforeEach(function () {
    $this->user  = User::factory()->create();
    $this->other = User::factory()->create();
});

test('a repeated term updates its row instead of appending one', function () {
    // The palette searches on every keystroke. If a repeat appended, typing
    // "invoice" once would file i, in, inv, invo, invoi, invoic, invoice and
    // the recent list would show a single word being spelled out.
    SearchHistory::remember($this->user->id, 'invoice', 3);
    SearchHistory::remember($this->user->id, 'invoice', 9);

    expect(SearchHistory::query()->where('user_id', $this->user->id)->count())->toBe(1)
        ->and(SearchHistory::query()->first()->result_count)->toBe(9);
});

test('recents are the caller own rows, newest first', function () {
    SearchHistory::remember($this->user->id, 'older', 1);
    $this->travel(2)->minutes();
    SearchHistory::remember($this->user->id, 'newer', 2);
    SearchHistory::remember($this->other->id, 'not yours', 1);

    $terms = $this->actingAs($this->user)
        ->getJson('/api/v1/search/history')
        ->assertOk()
        ->json('data.*.term');

    expect($terms)->toBe(['newer', 'older']);
});

test('storing records the term for the caller', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/search/history', ['term' => 'quarterly', 'result_count' => 4])
        ->assertNoContent();

    $this->assertDatabaseHas('search_histories', [
        'user_id'      => $this->user->id,
        'term'         => 'quarterly',
        'result_count' => 4,
    ]);
});

test('a one-character term is refused, matching the search endpoint', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/search/history', ['term' => 'q'])
        ->assertStatus(422);
});

test('deleting someone else entry is a 404, and leaves it alone', function () {
    // A 404 rather than a 403: whether that id exists is not the caller's to
    // learn. A history row records what somebody went looking for, which is
    // worth as much as the results were.
    $theirs = SearchHistory::remember($this->other->id, 'private matter', 1);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/search/history/{$theirs->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('search_histories', ['id' => $theirs->id]);
});

test('clearing removes only the caller rows', function () {
    SearchHistory::remember($this->user->id, 'mine one', 1);
    SearchHistory::remember($this->user->id, 'mine two', 1);
    SearchHistory::remember($this->other->id, 'theirs', 1);

    $this->actingAs($this->user)->deleteJson('/api/v1/search/history')->assertNoContent();

    expect(SearchHistory::query()->where('user_id', $this->user->id)->count())->toBe(0)
        ->and(SearchHistory::query()->where('user_id', $this->other->id)->count())->toBe(1);
});

test('history requires a signed-in user', function () {
    $this->getJson('/api/v1/search/history')->assertUnauthorized();
    $this->postJson('/api/v1/search/history', ['term' => 'anything'])->assertUnauthorized();
    $this->deleteJson('/api/v1/search/history')->assertUnauthorized();
});
