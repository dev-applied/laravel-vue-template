<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Favorites\Models\Favorite;
use Modules\Favorites\Support\FavoritableRegistry;
use Modules\Favorites\Tests\Support\FavoritableItem;

/**
 * A favourite looks trivial and is not: it is a per-user record keyed to an
 * arbitrary model named in the URL, and its list endpoint reads labels back out
 * of those models. Both halves are the interesting part.
 */
beforeEach(function () {
    $this->user  = User::factory()->create();
    $this->other = User::factory()->create();
    $this->item  = FavoritableItem::create(FavoritableItem::factory()->raw());

    // No blanket Gate::before here. The default registration below passes
    // ability: null, so nothing is checked unless a test opts in — and a
    // catch-all `before` would silently defeat the two tests that do.
    app(FavoritableRegistry::class)->register('item', FavoritableItem::class, ability: null);
});

function toggleFavorite(User $user, string $type, $id)
{
    return test()->actingAs($user)->postJson("/api/v1/favorites/{$type}/{$id}");
}

// ---------------------------------------------------------------------------
// Toggling
// ---------------------------------------------------------------------------

test('toggling stars a record, and toggling again unstars it', function () {
    toggleFavorite($this->user, 'item', $this->item->id)
        ->assertOk()
        ->assertJsonPath('favorited', true);

    expect(Favorite::count())->toBe(1);

    toggleFavorite($this->user, 'item', $this->item->id)
        ->assertOk()
        ->assertJsonPath('favorited', false);

    expect(Favorite::count())->toBe(0);
});

test('a star is a set membership, not an event', function () {
    // Two tabs, one star. Without the unique index the list shows the record
    // twice and un-starring removes one of them, which looks broken.
    Favorite::query()->create([
        'user_id'          => $this->user->id,
        'favoritable_type' => $this->item->getMorphClass(),
        'favoritable_id'   => $this->item->id,
    ]);

    expect(fn () => Favorite::query()->create([
        'user_id'          => $this->user->id,
        'favoritable_type' => $this->item->getMorphClass(),
        'favoritable_id'   => $this->item->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

test('two users starring the same record are independent', function () {
    toggleFavorite($this->user, 'item', $this->item->id)->assertOk();
    toggleFavorite($this->other, 'item', $this->item->id)->assertOk();

    expect(Favorite::count())->toBe(2);

    // One un-starring must not touch the other's.
    toggleFavorite($this->user, 'item', $this->item->id)->assertJsonPath('favorited', false);

    expect(Favorite::where('user_id', $this->other->id)->count())->toBe(1);
});

test('unfavouriting is idempotent', function () {
    // A retried delete should give the same answer, not a 404 that reads like
    // the record vanished.
    $this->actingAs($this->user)
        ->deleteJson("/api/v1/favorites/item/{$this->item->id}")
        ->assertOk()
        ->assertJsonPath('favorited', false);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/favorites/item/{$this->item->id}")
        ->assertOk()
        ->assertJsonPath('favorited', false);
});

// ---------------------------------------------------------------------------
// The allow-list
// ---------------------------------------------------------------------------

test('an unregistered type is refused', function () {
    // Without the registry the type reaches the model layer straight from the
    // URL, and starring becomes a way to confirm any record id exists.
    toggleFavorite($this->user, 'user', $this->other->id)->assertNotFound();

    expect(Favorite::count())->toBe(0);
});

test('an unregistered type is a 404, not a 403', function () {
    // Which models this app happens to have is not something to hand out. From
    // outside, "not favouritable" and "does not exist" are the same answer.
    toggleFavorite($this->user, 'secretmodel', 1)->assertNotFound();
});

test('a registered type with a missing record is still a 404', function () {
    toggleFavorite($this->user, 'item', 999999)->assertNotFound();
});

// ---------------------------------------------------------------------------
// Authorization — the half that is easy to miss
// ---------------------------------------------------------------------------

test('a record the user cannot view cannot be favourited', function () {
    // This is the reason the registry takes an ability at all. The list
    // endpoint reads a LABEL back out of every starred record, so without a
    // check, starring is a way to read the title of anything you can name —
    // and un-starring tells you whether it existed.
    app(FavoritableRegistry::class)->register('item', FavoritableItem::class, ability: 'view');
    Gate::define('view', fn () => false);

    // 404 and not 403: findOrFail() already answers 404 for a record that
    // does not exist, so a 403 here would be the difference that tells a
    // caller which ids are real.
    toggleFavorite($this->user, 'item', $this->item->id)->assertNotFound();

    expect(Favorite::count())->toBe(0);
});

test('the ability is checked on unfavouriting too', function () {
    app(FavoritableRegistry::class)->register('item', FavoritableItem::class, ability: 'view');
    Gate::define('view', fn () => false);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/favorites/item/{$this->item->id}")
        ->assertNotFound();
});

test('the whole surface requires authentication', function () {
    $this->postJson("/api/v1/favorites/item/{$this->item->id}")->assertUnauthorized();
    $this->getJson('/api/v1/favorites')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// The list
// ---------------------------------------------------------------------------

test('the list shows only the caller own favourites', function () {
    toggleFavorite($this->user, 'item', $this->item->id)->assertOk();
    toggleFavorite($this->other, 'item', $this->item->id)->assertOk();

    // No user id is taken from the request anywhere, so there is no parameter
    // to tamper with — but assert the outcome rather than the absence.
    $data = $this->actingAs($this->other)->getJson('/api/v1/favorites')->assertOk()->json('data');

    expect($data)->toHaveCount(1);
});

test('the list returns the registry alias, never the class name', function () {
    // App\Models\Whatever tells a caller the app's namespace layout, and the
    // alias is what they have to send back anyway.
    toggleFavorite($this->user, 'item', $this->item->id);

    $row = $this->actingAs($this->user)->getJson('/api/v1/favorites')->assertOk()->json('data.0');

    expect($row['type'])->toBe('item')
        ->and(json_encode($row))->not->toContain('Modules\\')
        ->and(json_encode($row))->not->toContain('App\\Models');
});

test('a favourite outlives its target without breaking the list', function () {
    // A hard-deleted record leaves the row pointing at nothing. The list has to
    // render that as unavailable rather than failing the whole page.
    toggleFavorite($this->user, 'item', $this->item->id);
    $this->item->forceDelete();

    $row = $this->actingAs($this->user)->getJson('/api/v1/favorites')->assertOk()->json('data.0');

    expect($row['record'])->toBeNull();
});

test('the list can be filtered to one type', function () {
    toggleFavorite($this->user, 'item', $this->item->id);

    expect($this->actingAs($this->user)->getJson('/api/v1/favorites?type=item')->json('data'))->toHaveCount(1)
        // An unregistered filter value is ignored rather than erroring — it is
        // a filter, not an identifier, and 404ing a list is unhelpful.
        ->and($this->actingAs($this->user)->getJson('/api/v1/favorites?type=nonsense')->json('data'))->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// The trait
// ---------------------------------------------------------------------------

test('isFavoritedBy answers for the given user only', function () {
    toggleFavorite($this->user, 'item', $this->item->id);

    $item = FavoritableItem::find($this->item->id);

    expect($item->isFavoritedBy($this->user))->toBeTrue()
        ->and($item->isFavoritedBy($this->other))->toBeFalse()
        // A guest has no favourites — fail closed rather than throwing.
        ->and($item->isFavoritedBy(null))->toBeFalse();
});

test('withFavoritedBy loads only that user rows, and isFavoritedBy uses them', function () {
    toggleFavorite($this->user, 'item', $this->item->id);
    toggleFavorite($this->other, 'item', $this->item->id);

    $item = FavoritableItem::query()->withFavoritedBy($this->user)->find($this->item->id);

    // Loading every user's rows would be both wasteful and a disclosure: how
    // many other people starred a record is not a list endpoint's business.
    expect($item->favorites)->toHaveCount(1)
        ->and($item->isFavoritedBy($this->user))->toBeTrue();
});

test('favoritedBy scopes a query to one user starred records', function () {
    $other = FavoritableItem::create(FavoritableItem::factory()->raw());
    toggleFavorite($this->user, 'item', $this->item->id);

    $ids = FavoritableItem::query()->favoritedBy($this->user)->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and($ids->first())->toBe($this->item->id)
        // A guest sees none, rather than all.
        ->and(FavoritableItem::query()->favoritedBy(null)->count())->toBe(0);
});

test('deleting a user takes their favourites with them', function () {
    toggleFavorite($this->user, 'item', $this->item->id);

    $this->user->delete();

    expect(Favorite::count())->toBe(0);
});
