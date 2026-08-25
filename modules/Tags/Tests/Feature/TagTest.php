<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\Tags\Models\Tag;
use Modules\Tags\Support\TaggableRegistry;
use Modules\Tags\Support\TagPoolScope;
use Modules\Tags\Tests\Support\ScopedTaggableItem;
use Modules\Tags\Tests\Support\TaggableItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->item = TaggableItem::create(TaggableItem::factory()->raw());

    Gate::define('manage-tags', fn () => true);
    app(TaggableRegistry::class)->register('item', TaggableItem::class, ability: null);
});

test('tagging a record creates the tag and the link', function () {
    $this->item->syncTags(['Urgent', 'Billing']);

    expect($this->item->tags()->pluck('name')->sort()->values()->all())->toBe(['Billing', 'Urgent'])
        ->and(Tag::count())->toBe(2);
});

test('case and spacing collapse to one tag', function () {
    // Otherwise a tag list becomes three near-duplicates and no filter on it
    // is trustworthy.
    $this->item->syncTags(['Urgent']);

    $other = TaggableItem::create(TaggableItem::factory()->raw());
    $other->syncTags(['  urgent ']);

    expect(Tag::count())->toBe(1)
        ->and($other->tags()->first()->id)->toBe($this->item->tags()->first()->id);
});

test('punctuation alone does not make a second tag', function () {
    // Str::slug drops it, so "Urgent!" and "urgent" are the same tag. Worth
    // pinning: it is the behaviour that makes a tag list stay short.
    $this->item->syncTags(['urgent']);

    $other = TaggableItem::create(TaggableItem::factory()->raw());
    $other->syncTags(['Urgent!']);

    expect(Tag::count())->toBe(1);
});

test('tagging the same record twice is a no-op, not a duplicate row', function () {
    $this->item->attachTags(['Urgent']);
    $this->item->attachTags(['Urgent']);

    expect($this->item->tags()->count())->toBe(1);
});

test('attaching an already-present tag does not blow up on the unique index', function () {
    // attach() would; syncWithoutDetaching() does not.
    $this->item->syncTags(['Urgent', 'Billing']);

    $this->item->attachTags(['Urgent', 'New']);

    expect($this->item->fresh()->tags()->pluck('name')->sort()->values()->all())
        ->toBe(['Billing', 'New', 'Urgent']);
});

test('syncing replaces the whole set', function () {
    $this->item->syncTags(['A', 'B']);
    $this->item->syncTags(['B', 'C']);

    expect($this->item->fresh()->tags()->pluck('name')->sort()->values()->all())->toBe(['B', 'C']);
});

test('blank names are ignored rather than creating an empty tag', function () {
    $this->item->syncTags(['Real', '', '   ']);

    expect(Tag::count())->toBe(1);
});

test('usage_count is counted, not incremented', function () {
    // An increment drifts the first time a record loses a tag, and a wrong
    // count is worse than none because the picker sorts on it.
    $second = TaggableItem::create(TaggableItem::factory()->raw());

    $this->item->syncTags(['Shared']);
    $second->syncTags(['Shared']);

    expect(Tag::where('name', 'Shared')->first()->usage_count)->toBe(2);

    $this->item->syncTags([]);

    expect(Tag::where('name', 'Shared')->first()->usage_count)->toBe(1);
});

test('a scoped model keeps its tags separate from the global pool', function () {
    $this->item->syncTags(['Urgent']);

    $scoped = ScopedTaggableItem::create(ScopedTaggableItem::factory()->raw());
    $scoped->syncTags(['Urgent']);

    expect(Tag::count())->toBe(2)
        ->and(Tag::whereNull('type')->count())->toBe(1)
        ->and(Tag::where('type', 'item')->count())->toBe(1);
});

test('withAllTags narrows rather than widens', function () {
    // "urgent" plus "billing" should mean the overlap. An OR filter that
    // widens the list as you add terms is the surprising one.
    $both = TaggableItem::create(TaggableItem::factory()->raw());
    $one  = TaggableItem::create(TaggableItem::factory()->raw());

    $both->syncTags(['urgent', 'billing']);
    $one->syncTags(['urgent']);

    $ids = TaggableItem::withAllTags(['urgent', 'billing'])->pluck('id')->all();

    expect($ids)->toBe([$both->id]);
});

test('withAnyTags returns the union', function () {
    $a = TaggableItem::create(TaggableItem::factory()->raw());
    $b = TaggableItem::create(TaggableItem::factory()->raw());

    $a->syncTags(['urgent']);
    $b->syncTags(['billing']);

    $ids = TaggableItem::withAnyTags(['urgent', 'billing'])->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

test('an empty tag filter does not filter anything out', function () {
    TaggableItem::create(TaggableItem::factory()->raw());

    expect(TaggableItem::withAnyTags([])->count())->toBe(TaggableItem::count());
});

test('the pool lists most-used first', function () {
    // The tag someone wants is usually one they have used before.
    $a = TaggableItem::create(TaggableItem::factory()->raw());
    $b = TaggableItem::create(TaggableItem::factory()->raw());

    $a->syncTags(['popular', 'rare']);
    $b->syncTags(['popular']);

    $names = $this->actingAs($this->user)->getJson('/api/v1/tags')->assertOk()->json('tags.*.name');

    expect($names[0])->toBe('popular');
});

test('the pool can be searched', function () {
    $this->item->syncTags(['invoicing', 'shipping']);

    $names = $this->actingAs($this->user)
        ->getJson('/api/v1/tags?search=ship')
        ->json('tags.*.name');

    expect($names)->toBe(['shipping']);
});

test('the pool is scoped by type', function () {
    $this->item->syncTags(['global-one']);
    $scoped = ScopedTaggableItem::create(ScopedTaggableItem::factory()->raw());
    $scoped->syncTags(['scoped-one']);

    $global = $this->actingAs($this->user)->getJson('/api/v1/tags')->json('tags.*.name');
    $typed  = $this->actingAs($this->user)->getJson('/api/v1/tags?type=item')->json('tags.*.name');

    expect($global)->toBe(['global-one'])
        ->and($typed)->toBe(['scoped-one']);
});

test('a record tag list is readable and writable through the API', function () {
    $this->actingAs($this->user)
        ->putJson("/api/v1/tags/item/{$this->item->id}", ['tags' => ['alpha', 'beta']])
        ->assertOk()
        ->assertJsonCount(2, 'tags');

    $this->actingAs($this->user)
        ->getJson("/api/v1/tags/item/{$this->item->id}")
        ->assertOk()
        ->assertJsonPath('tags.0.name', 'alpha');
});

test('an unregistered type is rejected', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/tags/user/1')
        ->assertNotFound();
});

test('the registered ability gates reading as well as writing', function () {
    // A tag list can leak how a record has been categorised internally.
    app(TaggableRegistry::class)->register('item', TaggableItem::class, ability: 'tag-it');
    Gate::define('tag-it', fn () => false);

    // 404 rather than 403 — a 403 would confirm the record exists, which
    // with sequential ids turns this endpoint into a table census.
    $this->actingAs($this->user)->getJson("/api/v1/tags/item/{$this->item->id}")->assertNotFound();
    $this->actingAs($this->user)->putJson("/api/v1/tags/item/{$this->item->id}", ['tags' => []])->assertNotFound();
});

test('renaming a tag onto an existing one is refused', function () {
    // Silently colliding would break the unique index or orphan every record
    // on one of them.
    $this->item->syncTags(['keep', 'other']);
    $other = Tag::where('name', 'other')->first();

    $this->actingAs($this->user)
        ->putJson("/api/v1/tags/{$other->id}", ['name' => 'keep'])
        ->assertStatus(422);
});

test('renaming a tag updates its slug', function () {
    $this->item->syncTags(['before']);
    $tag = Tag::first();

    $this->actingAs($this->user)
        ->putJson("/api/v1/tags/{$tag->id}", ['name' => 'After Rename'])
        ->assertOk();

    expect($tag->fresh()->slug)->toBe('after-rename');
});

test('merging keeps every record that carried either tag', function () {
    // Two genuinely different tags a human would want folded together —
    // punctuation alone would not make two, since it normalises away.
    $a = TaggableItem::create(TaggableItem::factory()->raw());
    $b = TaggableItem::create(TaggableItem::factory()->raw());

    $a->syncTags(['urgent']);
    $b->syncTags(['high priority']);

    $from = Tag::where('name', 'high priority')->firstOrFail();
    $into = Tag::where('name', 'urgent')->firstOrFail();

    $this->actingAs($this->user)
        ->postJson("/api/v1/tags/{$from->id}/merge", ['into' => $into->id])
        ->assertOk();

    expect(Tag::count())->toBe(1)
        ->and($into->fresh()->usage_count)->toBe(2);
});

test('merging a tag a record already has does not break halfway', function () {
    // insertOrIgnore, because the unique index would otherwise fire mid-merge
    // and leave it half-done.
    $this->item->syncTags(['urgent', 'high priority']);

    $from = Tag::where('name', 'high priority')->firstOrFail();
    $into = Tag::where('name', 'urgent')->firstOrFail();

    $this->actingAs($this->user)
        ->postJson("/api/v1/tags/{$from->id}/merge", ['into' => $into->id])
        ->assertOk();

    expect($into->fresh()->usage_count)->toBe(1);
});

test('a tag cannot be merged into itself', function () {
    $this->item->syncTags(['solo']);
    $tag = Tag::first();

    $this->actingAs($this->user)
        ->postJson("/api/v1/tags/{$tag->id}/merge", ['into' => $tag->id])
        ->assertStatus(422);
});

test('deleting a tag leaves the records alone', function () {
    $this->item->syncTags(['doomed']);
    $tag = Tag::first();

    $this->actingAs($this->user)->deleteJson("/api/v1/tags/{$tag->id}")->assertOk();

    expect(TaggableItem::find($this->item->id))->not->toBeNull()
        ->and($this->item->fresh()->tags()->count())->toBe(0);
});

test('curating the pool needs the manage-tags ability', function () {
    Gate::define('manage-tags', fn () => false);
    $this->item->syncTags(['x']);
    $tag = Tag::first();

    $this->actingAs($this->user)->putJson("/api/v1/tags/{$tag->id}", ['name' => 'y'])->assertForbidden();
    $this->actingAs($this->user)->deleteJson("/api/v1/tags/{$tag->id}")->assertForbidden();
});

test('reading the pool does not need manage-tags', function () {
    Gate::define('manage-tags', fn () => false);

    $this->actingAs($this->user)->getJson('/api/v1/tags')->assertOk();
});

test('tags require authentication', function () {
    $this->getJson('/api/v1/tags')->assertUnauthorized();
});

test('the dedupe command folds legacy duplicates together', function () {
    // A project adding this module to an existing database inherits whatever
    // is already there — usually the same word three times.
    $keep = Tag::create(['name' => 'Urgent', 'slug' => 'urgent', 'usage_count' => 5]);
    Tag::create(['name' => 'urgent', 'slug' => 'urgent-2', 'usage_count' => 1]);
    Tag::create(['name' => 'URGENT', 'slug' => 'urgent-3', 'usage_count' => 0]);

    $this->artisan('tags:dedupe')->assertSuccessful();

    expect(Tag::count())->toBe(1)
        ->and(Tag::first()->id)->toBe($keep->id);
});

test('the dedupe dry run changes nothing', function () {
    Tag::create(['name' => 'Urgent', 'slug' => 'urgent']);
    Tag::create(['name' => 'urgent', 'slug' => 'urgent-2']);

    $this->artisan('tags:dedupe --dry-run')->assertSuccessful();

    expect(Tag::count())->toBe(2);
});

// ── The pool ─────────────────────────────────────────────────────────────────
// Tags ON a record are gated by the registered ability. The pool endpoint had
// no gate at all, and tag names carry the internal judgement a project does not
// publish — at-risk, legal-hold, vip — with a usage_count beside each one.

test('a bound scope can refuse a tag pool, and refuses it as a 404', function () {
    app()->bind(TagPoolScope::class, fn () => new class implements TagPoolScope
    {
        public function allows(?string $type, mixed $user): bool
        {
            return $type !== 'legal';
        }

        public function apply(Builder $query, mixed $user): void {}
    });

    Tag::factory()->create(['name' => 'legal-hold', 'type' => 'legal']);

    // 404 and not 403: a refusal that differs from "there is nothing here"
    // tells the caller which pools exist.
    $this->actingAs($this->user)->getJson('/api/v1/tags?type=legal')->assertNotFound();
    $this->actingAs($this->user)->getJson('/api/v1/tags?type=general')->assertOk();
});

test('a bound scope narrows the pool query itself', function () {
    app()->bind(TagPoolScope::class, fn () => new class implements TagPoolScope
    {
        public function allows(?string $type, mixed $user): bool
        {
            return true;
        }

        public function apply(Builder $query, mixed $user): void
        {
            $query->where('name', 'like', 'ok-%');
        }
    });

    Tag::factory()->create(['name' => 'ok-visible', 'type' => null]);
    Tag::factory()->create(['name' => 'hidden-one', 'type' => null]);

    $names = $this->actingAs($this->user)->getJson('/api/v1/tags')->assertOk()->json('tags.*.name');

    expect($names)->toBe(['ok-visible']);
});

test('the shipped default allows every pool', function () {
    // Permissive on purpose, and pinned so it cannot drift: a module that
    // refused unknown types would make tag autocomplete vanish on install for
    // reasons nobody could trace. Same call SavedViews made.
    Tag::factory()->create(['name' => 'anything', 'type' => 'legal']);

    $this->actingAs($this->user)->getJson('/api/v1/tags?type=legal')->assertOk();
});
