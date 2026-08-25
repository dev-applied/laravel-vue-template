<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\SavedViews\Models\SavedView;
use Modules\SavedViews\Support\SavedViewScope;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('a user saves the filters they are looking at', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/saved-views', [
            'key'     => 'items.index',
            'name'    => 'Open, mine, newest',
            'payload' => ['filters' => ['status' => 'open', 'owner' => 'me'], 'sortBy' => [['key' => 'created_at', 'order' => 'desc']]],
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Open, mine, newest')
        ->assertJsonPath('payload.filters.status', 'open')
        ->assertJsonPath('isOwn', true);
});

test('the payload round-trips whatever shape the screen sent', function () {
    // The module never interprets the payload — that is the screen's business.
    $payload = [
        'filters'      => ['search' => 'widget', 'tags' => ['a', 'b'], 'archived' => false],
        'sortBy'       => [['key' => 'name', 'order' => 'asc']],
        'itemsPerPage' => 50,
        'columns'      => ['name', 'status', 'created_at'],
    ];

    $this->actingAs($this->user)
        ->postJson('/api/v1/saved-views', ['key' => 'items.index', 'name' => 'Complex', 'payload' => $payload])
        ->assertCreated();

    $stored = $this->actingAs($this->user)
        ->getJson('/api/v1/saved-views?key=items.index')
        ->json('views.0.payload');

    // Compared key-order-insensitively at the object level, and strictly for
    // LISTS — because that is exactly the guarantee the storage gives.
    //
    // `payload` is a MySQL `json` column, and MySQL's native JSON type is a
    // normalised binary format: it sorts object keys (by length, then
    // lexicographically) and drops duplicates. MariaDB's JSON is LONGTEXT plus
    // a CHECK, so it hands back the literal string. A byte-identity assertion
    // therefore passes on MariaDB and fails on MySQL 8 — which is how this test
    // came to be written, and what the sqlite-to-MySQL switch caught on its
    // first run.
    //
    // Nothing semantic is lost: JSON ARRAY order is preserved by both, so
    // `columns` and `sortBy` — the parts where order carries meaning — round
    // trip exactly. Only the order of keys within an object moves, and no
    // consumer of a saved view can reasonably depend on that.
    expect(deepKsort($stored))->toBe(deepKsort($payload))
        ->and($stored['columns'])->toBe(['name', 'status', 'created_at'])
        ->and($stored['sortBy'])->toBe([['key' => 'name', 'order' => 'asc']])
        ->and($stored['filters']['tags'])->toBe(['a', 'b']);
});

/** Sort object keys at every depth, leaving list order alone. */
function deepKsort(array $value): array
{
    foreach ($value as $k => $v) {
        if (is_array($v)) {
            $value[$k] = deepKsort($v);
        }
    }

    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
}

test('views are scoped to one screen', function () {
    // A view for one table must never surface on another.
    SavedView::factory()->for($this->user)->create(['key' => 'items.index', 'name' => 'Items view']);
    SavedView::factory()->for($this->user)->create(['key' => 'orders.index', 'name' => 'Orders view']);

    $names = $this->actingAs($this->user)
        ->getJson('/api/v1/saved-views?key=items.index')
        ->assertOk()
        ->json('views.*.name');

    expect($names)->toBe(['Items view']);
});

test('the index requires a key', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/saved-views')
        ->assertStatus(422)
        ->assertJsonValidationErrors('key');
});

test('another user private view is invisible', function () {
    $other = User::factory()->create();
    SavedView::factory()->for($other)->create(['key' => 'items.index', 'name' => 'Theirs']);

    $this->actingAs($this->user)
        ->getJson('/api/v1/saved-views?key=items.index')
        ->assertJsonPath('views', []);
});

test('a shared view is visible to everyone on that screen', function () {
    $other = User::factory()->create();
    SavedView::factory()->for($other)->shared()->create(['key' => 'items.index', 'name' => 'Team triage']);

    $this->actingAs($this->user)
        ->getJson('/api/v1/saved-views?key=items.index')
        ->assertJsonCount(1, 'views')
        ->assertJsonPath('views.0.name', 'Team triage')
        // The picker uses this to decide whether to offer rename/delete, so it
        // never has to try and then get a 403.
        ->assertJsonPath('views.0.isOwn', false);
});

test('a shared view someone else owns cannot be renamed', function () {
    $other = User::factory()->create();
    $view  = SavedView::factory()->for($other)->shared()->create(['key' => 'items.index']);

    $this->actingAs($this->user)
        ->putJson("/api/v1/saved-views/{$view->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    expect($view->fresh()->name)->not->toBe('Hijacked');
});

test('a shared view someone else owns cannot be deleted', function () {
    // One person tidying their picker must not delete everyone else's view.
    $other = User::factory()->create();
    $view  = SavedView::factory()->for($other)->shared()->create(['key' => 'items.index']);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/saved-views/{$view->id}")
        ->assertForbidden();

    expect(SavedView::find($view->id))->not->toBeNull();
});

test('own views sort before shared ones', function () {
    $other = User::factory()->create();
    SavedView::factory()->for($other)->shared()->create(['key' => 'items.index', 'name' => 'AAA shared']);
    SavedView::factory()->for($this->user)->create(['key' => 'items.index', 'name' => 'ZZZ mine']);

    $names = $this->actingAs($this->user)
        ->getJson('/api/v1/saved-views?key=items.index')
        ->json('views.*.name');

    expect($names)->toBe(['ZZZ mine', 'AAA shared']);
});

test('setting a default clears the previous one', function () {
    // Two defaults means the screen opens on whichever row sorted first — a
    // bug that looks random to the person hitting it.
    $first  = SavedView::factory()->for($this->user)->default()->create(['key' => 'items.index', 'name' => 'First']);
    $second = SavedView::factory()->for($this->user)->create(['key' => 'items.index', 'name' => 'Second']);

    $this->actingAs($this->user)
        ->putJson("/api/v1/saved-views/{$second->id}", ['is_default' => true])
        ->assertOk();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

test('a default on one screen does not clear a default on another', function () {
    $items  = SavedView::factory()->for($this->user)->default()->create(['key' => 'items.index', 'name' => 'Items default']);
    $orders = SavedView::factory()->for($this->user)->create(['key' => 'orders.index', 'name' => 'Orders default']);

    $this->actingAs($this->user)
        ->putJson("/api/v1/saved-views/{$orders->id}", ['is_default' => true])
        ->assertOk();

    expect($items->fresh()->is_default)->toBeTrue();
});

test('one user default does not clear another user default', function () {
    $other  = User::factory()->create();
    $theirs = SavedView::factory()->for($other)->default()->create(['key' => 'items.index', 'name' => 'Theirs']);
    $mine   = SavedView::factory()->for($this->user)->create(['key' => 'items.index', 'name' => 'Mine']);

    $this->actingAs($this->user)
        ->putJson("/api/v1/saved-views/{$mine->id}", ['is_default' => true])
        ->assertOk();

    expect($theirs->fresh()->is_default)->toBeTrue();
});

test('creating a view as the default clears the old default too', function () {
    $old = SavedView::factory()->for($this->user)->default()->create(['key' => 'items.index', 'name' => 'Old']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/saved-views', [
            'key' => 'items.index', 'name' => 'New', 'payload' => ['filters' => []], 'is_default' => true,
        ])
        ->assertCreated();

    expect($old->fresh()->is_default)->toBeFalse();
});

test('two views on one screen cannot share a name', function () {
    // The picker would show them identically — that is a naming mistake, not
    // a feature. Caught in validation so the person gets told which word to
    // change, rather than a 500 from the unique index.
    SavedView::factory()->for($this->user)->create(['key' => 'items.index', 'name' => 'Triage']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/saved-views', ['key' => 'items.index', 'name' => 'Triage', 'payload' => ['filters' => []]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('two users may each have a view of the same name', function () {
    // The uniqueness is per person per screen, not global.
    $other = User::factory()->create();
    SavedView::factory()->for($other)->create(['key' => 'items.index', 'name' => 'Triage']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/saved-views', ['key' => 'items.index', 'name' => 'Triage', 'payload' => ['filters' => []]])
        ->assertCreated();
});

test('renaming a view to its own name is not a conflict', function () {
    $view = SavedView::factory()->for($this->user)->create(['key' => 'items.index', 'name' => 'Triage']);

    $this->actingAs($this->user)
        ->putJson("/api/v1/saved-views/{$view->id}", ['name' => 'Triage', 'is_default' => true])
        ->assertOk();
});

test('renaming a view onto another of your names is rejected', function () {
    SavedView::factory()->for($this->user)->create(['key' => 'items.index', 'name' => 'Triage']);
    $other = SavedView::factory()->for($this->user)->create(['key' => 'items.index', 'name' => 'Backlog']);

    $this->actingAs($this->user)
        ->putJson("/api/v1/saved-views/{$other->id}", ['name' => 'Triage'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('the same name on two different screens is fine', function () {
    SavedView::factory()->for($this->user)->create(['key' => 'items.index', 'name' => 'Triage']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/saved-views', ['key' => 'orders.index', 'name' => 'Triage', 'payload' => ['filters' => []]])
        ->assertCreated();
});

test('a view cannot be moved to another screen', function () {
    // Moving it would apply filters the target screen does not have.
    $view = SavedView::factory()->for($this->user)->create(['key' => 'items.index']);

    $this->actingAs($this->user)
        ->putJson("/api/v1/saved-views/{$view->id}", ['key' => 'orders.index', 'name' => 'Renamed'])
        ->assertOk();

    expect($view->fresh()->key)->toBe('items.index');
});

test('a user deletes their own view', function () {
    $view = SavedView::factory()->for($this->user)->create(['key' => 'items.index']);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/saved-views/{$view->id}")
        ->assertOk();

    expect(SavedView::find($view->id))->toBeNull();
});

test('deleting the user takes their views with them', function () {
    $view = SavedView::factory()->for($this->user)->create(['key' => 'items.index']);

    $this->user->delete();

    expect(SavedView::find($view->id))->toBeNull();
});

test('an oversized payload is rejected', function () {
    // A saved view is a filter set, not a place to park a dataset.
    $this->actingAs($this->user)
        ->postJson('/api/v1/saved-views', [
            'key'     => 'items.index',
            'name'    => 'Huge',
            'payload' => array_fill_keys(array_map(fn ($i) => "k$i", range(1, 100)), 'v'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('payload');
});

test('saved views require authentication', function () {
    $this->getJson('/api/v1/saved-views?key=items.index')->assertUnauthorized();
    $this->postJson('/api/v1/saved-views', [])->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Screen-key access
// ---------------------------------------------------------------------------

test('a scope can refuse a screen, and refusing hides it entirely', function () {
    // `key` arrives as a free string with no allow-list, so without a screen
    // check a low-privilege user could guess `admin.users.index` and read back
    // every SHARED view on it — payload and owner name included. A payload is
    // filters, sort and columns, which in practice carry record ids and search
    // terms.
    app()->instance(SavedViewScope::class, new class implements SavedViewScope
    {
        public function apply(Builder $query, mixed $user): void {}

        public function attributes(mixed $user): array
        {
            return [];
        }

        public function allows(string $key, mixed $user): bool
        {
            return ! str_starts_with($key, 'admin.');
        }
    });

    SavedView::factory()->create(['key' => 'admin.users.index', 'is_shared' => true]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/saved-views?key=admin.users.index')
        ->assertNotFound();

    $this->actingAs($this->user)
        ->getJson('/api/v1/saved-views?key=items.index')
        ->assertOk();
});

test('a refused screen cannot be written to either', function () {
    // The sharper half: a view marked is_shared on a screen you cannot open
    // is a row planted in the picker of everyone who can.
    app()->instance(SavedViewScope::class, new class implements SavedViewScope
    {
        public function apply(Builder $query, mixed $user): void {}

        public function attributes(mixed $user): array
        {
            return [];
        }

        public function allows(string $key, mixed $user): bool
        {
            return ! str_starts_with($key, 'admin.');
        }
    });

    $this->actingAs($this->user)
        ->postJson('/api/v1/saved-views', [
            'key'       => 'admin.users.index',
            'name'      => 'Planted',
            'payload'   => ['filters' => []],
            'is_shared' => true,
        ])
        ->assertNotFound();

    expect(SavedView::query()->count())->toBe(0);
});

test('the shipped NullScope allows every screen, on purpose', function () {
    // A module-level default that refused unknown keys would make saved views
    // vanish on install with no traceable cause. The refusal belongs to the
    // project, which is the only party that knows which screens are privileged.
    $this->actingAs($this->user)
        ->getJson('/api/v1/saved-views?key=anything.at.all')
        ->assertOk();
});
