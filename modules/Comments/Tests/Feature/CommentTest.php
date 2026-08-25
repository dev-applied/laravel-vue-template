<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Comments\Events\UserMentioned;
use Modules\Comments\Models\Comment;
use Modules\Comments\Support\CommentableRegistry;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->item = Item::factory()->create();

    // Anything unregistered is rejected, so the test registers what it uses.
    app(CommentableRegistry::class)->register('item', Item::class, ability: null);
});

test('a comment is attached to any registered model', function () {
    $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Looks good to me.'])
        ->assertCreated()
        ->assertJsonPath('body', 'Looks good to me.')
        ->assertJsonPath('canEdit', true);

    expect(Comment::count())->toBe(1);
});

test('comments come back oldest first', function () {
    $this->actingAs($this->user)->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'First']);
    $this->actingAs($this->user)->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Second']);

    $bodies = $this->actingAs($this->user)
        ->getJson("/api/v1/comments/item/{$this->item->id}")
        ->assertOk()
        ->json('comments.*.body');

    expect($bodies)->toBe(['First', 'Second']);
});

test('an unregistered type is rejected', function () {
    // Without the allow-list the endpoint would attach a comment to any
    // Eloquent class in the app.
    $this->actingAs($this->user)
        ->postJson('/api/v1/comments/user/1', ['body' => 'Nope'])
        ->assertNotFound();
});

test('a registered type still runs the project ability', function () {
    // A distinct ability name: `view` would be shadowed by the auto-discovered
    // ItemPolicy, which is correct behaviour but not what this asserts.
    app(CommentableRegistry::class)->register('item', Item::class, ability: 'comment-on');
    Gate::define('comment-on', fn () => false);

    // 404, not 403. A 403 confirms the record exists, and ids are
    // sequential — probing /comments/item/1..N and sorting 403 from 404
    // enumerates the table without ever reading a row.
    $this->actingAs($this->user)
        ->getJson("/api/v1/comments/item/{$this->item->id}")
        ->assertNotFound();
});

test('a registered ability that passes lets the request through', function () {
    app(CommentableRegistry::class)->register('item', Item::class, ability: 'comment-on');
    Gate::define('comment-on', fn () => true);

    $this->actingAs($this->user)
        ->getJson("/api/v1/comments/item/{$this->item->id}")
        ->assertOk();
});

test('the ability receives the resolved record, not just the type', function () {
    // So a project can allow comments on records the person owns and refuse
    // the rest, which is the common shape.
    $allowed = Item::factory()->create();
    app(CommentableRegistry::class)->register('item', Item::class, ability: 'comment-on');
    Gate::define('comment-on', fn ($user, $item) => $item->is($allowed));

    $this->actingAs($this->user)->getJson("/api/v1/comments/item/{$allowed->id}")->assertOk();
    $this->actingAs($this->user)->getJson("/api/v1/comments/item/{$this->item->id}")->assertNotFound();
});

test('a missing record is a 404, not a 500', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/comments/item/999999')
        ->assertNotFound();
});

test('comments on one record do not leak onto another', function () {
    $other = Item::factory()->create();
    $this->actingAs($this->user)->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Mine']);

    $this->actingAs($this->user)
        ->getJson("/api/v1/comments/item/{$other->id}")
        ->assertJsonPath('comments', []);
});

test('internal notes are hidden by default', function () {
    // The default is that nobody sees them; the project opens the door with
    // the see-internal-comments ability.
    Comment::factory()->internal()->create([
        'commentable_type' => $this->item->getMorphClass(),
        'commentable_id'   => $this->item->id,
        'body'             => 'Staff only',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/comments/item/{$this->item->id}")
        ->assertJsonPath('comments', []);
});

test('staff with the ability see internal notes', function () {
    Gate::define('see-internal-comments', fn () => true);
    Comment::factory()->internal()->create([
        'commentable_type' => $this->item->getMorphClass(),
        'commentable_id'   => $this->item->id,
        'body'             => 'Staff only',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/comments/item/{$this->item->id}")
        ->assertJsonCount(1, 'comments')
        ->assertJsonPath('comments.0.body', 'Staff only');
});

test('posting an internal note without the ability is refused', function () {
    // Otherwise anyone could file a note they cannot see, and staff would be
    // reading input from someone never meant to write there.
    $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Sneaky', 'is_internal' => true])
        ->assertForbidden();

    expect(Comment::count())->toBe(0);
});

test('a mention fires an event rather than assuming a notifier', function () {
    // The module must not assume the Notifications module is installed.
    Event::fake([UserMentioned::class]);
    $mentioned = User::factory()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", [
            'body' => "Can you look at this @[Jane Doe](user:{$mentioned->id})?",
        ])
        ->assertCreated();

    Event::assertDispatched(
        UserMentioned::class,
        fn (UserMentioned $e) => $e->user->is($mentioned)
    );
});

test('mentions are recorded on the comment', function () {
    $mentioned = User::factory()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", [
            'body' => "@[Jane Doe](user:{$mentioned->id}) please review",
        ])
        ->assertCreated()
        ->assertJsonPath('mentions.0.id', $mentioned->id);
});

test('mentioning the same person twice notifies once', function () {
    Event::fake([UserMentioned::class]);
    $mentioned = User::factory()->create();

    $this->actingAs($this->user)->postJson("/api/v1/comments/item/{$this->item->id}", [
        'body' => "@[Jane](user:{$mentioned->id}) and again @[Jane](user:{$mentioned->id})",
    ])->assertCreated();

    Event::assertDispatchedTimes(UserMentioned::class, 1);
});

test('mentioning yourself does not notify you', function () {
    Event::fake([UserMentioned::class]);

    $this->actingAs($this->user)->postJson("/api/v1/comments/item/{$this->item->id}", [
        'body' => "note to self @[Me](user:{$this->user->id})",
    ])->assertCreated();

    Event::assertNotDispatched(UserMentioned::class);
});

test('editing a comment does not re-notify people already mentioned', function () {
    // Re-notifying on a typo fix is what trains people to ignore mentions.
    $mentioned = User::factory()->create();

    $comment = $this->actingAs($this->user)->postJson("/api/v1/comments/item/{$this->item->id}", [
        'body' => "@[Jane](user:{$mentioned->id}) plese review",
    ])->json('id');

    Event::fake([UserMentioned::class]);

    $this->actingAs($this->user)
        ->putJson("/api/v1/comments/{$comment}", ['body' => "@[Jane](user:{$mentioned->id}) please review"])
        ->assertOk();

    Event::assertNotDispatched(UserMentioned::class);
});

test('adding a new mention in an edit notifies only the new person', function () {
    $first  = User::factory()->create();
    $second = User::factory()->create();

    $comment = $this->actingAs($this->user)->postJson("/api/v1/comments/item/{$this->item->id}", [
        'body' => "@[A](user:{$first->id}) look",
    ])->json('id');

    Event::fake([UserMentioned::class]);

    $this->actingAs($this->user)->putJson("/api/v1/comments/{$comment}", [
        'body' => "@[A](user:{$first->id}) @[B](user:{$second->id}) look",
    ])->assertOk();

    Event::assertDispatchedTimes(UserMentioned::class, 1);
    Event::assertDispatched(UserMentioned::class, fn (UserMentioned $e) => $e->user->is($second));
});

test('a mention of a user who does not exist is ignored', function () {
    Event::fake([UserMentioned::class]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => '@[Ghost](user:999999) hello'])
        ->assertCreated();

    Event::assertNotDispatched(UserMentioned::class);
});

test('an edit is stamped so the UI can mark it', function () {
    // A comment that silently changed after someone replied is worse than one
    // that says it changed.
    $comment = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Original'])
        ->json('id');

    expect(Comment::find($comment)->edited_at)->toBeNull();

    $this->actingAs($this->user)->putJson("/api/v1/comments/{$comment}", ['body' => 'Changed'])->assertOk();

    expect(Comment::find($comment)->edited_at)->not->toBeNull();
});

test('someone else comment cannot be edited', function () {
    $other   = User::factory()->create();
    $comment = Comment::factory()->for($other, 'author')->create([
        'commentable_type' => $this->item->getMorphClass(),
        'commentable_id'   => $this->item->id,
    ]);

    $this->actingAs($this->user)
        ->putJson("/api/v1/comments/{$comment->id}", ['body' => 'Hijacked'])
        ->assertForbidden();
});

test('someone else comment cannot be deleted', function () {
    $other   = User::factory()->create();
    $comment = Comment::factory()->for($other, 'author')->create([
        'commentable_type' => $this->item->getMorphClass(),
        'commentable_id'   => $this->item->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/comments/{$comment->id}")
        ->assertForbidden();

    expect(Comment::find($comment->id))->not->toBeNull();
});

test('a deleted author leaves the comment readable', function () {
    // The thread still has to make sense without the account.
    $other = User::factory()->create();
    $this->actingAs($other)->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Still here']);

    $other->delete();

    $this->actingAs($this->user)
        ->getJson("/api/v1/comments/item/{$this->item->id}")
        ->assertJsonCount(1, 'comments')
        ->assertJsonPath('comments.0.author.name', 'Deleted user');
});

test('comments require authentication', function () {
    $this->getJson("/api/v1/comments/item/{$this->item->id}")->assertUnauthorized();
    $this->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'x'])->assertUnauthorized();
});

test('an empty body is rejected', function () {
    $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');
});

// ── Access to the parent record is re-checked on every write ────────────────
// Ownership was the only check on edit and delete, and ownership does not
// expire. Access does.

test('losing access to the record revokes editing your own comments on it', function () {
    // Someone comments on an order, then is removed from the account that owns
    // it. Their comments were still fully writable: ownership still held, and
    // nothing re-asked whether they could still reach the order.
    app(CommentableRegistry::class)->register('item', Item::class, ability: 'comment-on');
    Gate::define('comment-on', fn () => true);

    $comment = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Mine'])
        ->assertCreated()
        ->json('id');

    // Access revoked.
    Gate::define('comment-on', fn () => false);

    $this->actingAs($this->user)
        ->putJson("/api/v1/comments/{$comment}", ['body' => 'Edited after losing access'])
        ->assertNotFound();

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/comments/{$comment}")
        ->assertNotFound();

    expect(Comment::find($comment)->body)->toBe('Mine');
});

test('editing cannot be used to notify people inside a record you can no longer open', function () {
    // Editing re-syncs mentions, so the write was not just a write — it was a
    // way to keep reaching the people on a record after being removed from it.
    Event::fake([UserMentioned::class]);

    app(CommentableRegistry::class)->register('item', Item::class, ability: 'comment-on');
    Gate::define('comment-on', fn () => true);

    $comment = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Mine'])
        ->assertCreated()
        ->json('id');

    $insider = User::factory()->create();
    Gate::define('comment-on', fn () => false);

    $this->actingAs($this->user)
        ->putJson("/api/v1/comments/{$comment}", ['body' => "@{$insider->id} are you seeing this"])
        ->assertNotFound();

    Event::assertNotDispatched(UserMentioned::class);
});

test('un-registering a type revokes writes to comments already on it', function () {
    // A project that stops exposing a type has to stop exposing its comments
    // too, not just its records.
    $comment = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Mine'])
        ->assertCreated()
        ->json('id');

    app()->forgetInstance(CommentableRegistry::class);
    app()->instance(CommentableRegistry::class, new CommentableRegistry);

    $this->actingAs($this->user)->putJson("/api/v1/comments/{$comment}", ['body' => 'x'])->assertNotFound();
    $this->actingAs($this->user)->deleteJson("/api/v1/comments/{$comment}")->assertNotFound();
});

test('the parent check runs before the ownership check', function () {
    // Order matters. Checking ownership first answers "that comment belongs to
    // someone else" — a 403 — for a record the caller cannot even reach, which
    // is the same existence oracle wearing a different hat. Past the parent
    // check the caller can already list the record's comments, so a 403 there
    // reveals nothing new.
    app(CommentableRegistry::class)->register('item', Item::class, ability: 'comment-on');
    Gate::define('comment-on', fn () => true);

    $owner   = User::factory()->create();
    $comment = $this->actingAs($owner)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Theirs'])
        ->assertCreated()
        ->json('id');

    // Reachable record, someone else's comment → 403 is fine.
    $this->actingAs($this->user)->putJson("/api/v1/comments/{$comment}", ['body' => 'x'])->assertForbidden();

    // Unreachable record → 404, so the comment's existence stays hidden.
    Gate::define('comment-on', fn () => false);
    $this->actingAs($this->user)->putJson("/api/v1/comments/{$comment}", ['body' => 'x'])->assertNotFound();
});
