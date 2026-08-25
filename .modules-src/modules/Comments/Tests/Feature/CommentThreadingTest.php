<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use Modules\Comments\Models\Comment;
use Modules\Comments\Support\CommentableRegistry;

/**
 * The `threaded` variant only. The `flat` choice drops this file.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->item = Item::factory()->create();
    app(CommentableRegistry::class)->register('item', Item::class, ability: null);
});

test('a reply nests under its parent', function () {
    $parent = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Question'])
        ->json('id');

    $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Answer', 'parent_id' => $parent])
        ->assertCreated()
        ->assertJsonPath('parentId', $parent);

    $response = $this->actingAs($this->user)->getJson("/api/v1/comments/item/{$this->item->id}");

    // One root comment with the reply inside it, not two roots.
    $response->assertJsonCount(1, 'comments')
        ->assertJsonPath('comments.0.replies.0.body', 'Answer');
});

test('nesting stops at one level', function () {
    // Arbitrary depth produces threads nobody can read and a recursive query
    // nobody can index. A reply-to-a-reply flattens to a reply.
    $parent = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Root'])
        ->json('id');

    $reply = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Reply', 'parent_id' => $parent])
        ->json('id');

    $deep = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Deep', 'parent_id' => $reply])
        ->assertCreated()
        ->json('parentId');

    expect($deep)->toBeNull();
});

test('a reply pointing at another record comment becomes a root comment', function () {
    // Otherwise it renders nowhere.
    $other   = Item::factory()->create();
    $foreign = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$other->id}", ['body' => 'Elsewhere'])
        ->json('id');

    $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Mine', 'parent_id' => $foreign])
        ->assertCreated()
        ->assertJsonPath('parentId', null);
});

test('deleting a comment takes its replies with it', function () {
    // A reply to nothing reads as a non-sequitur.
    $parent = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Root'])
        ->json('id');

    $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Reply', 'parent_id' => $parent]);

    $this->actingAs($this->user)->deleteJson("/api/v1/comments/{$parent}")->assertOk();

    expect(Comment::count())->toBe(0);
});

test('an internal reply stays hidden from someone without the ability', function () {
    $parent = $this->actingAs($this->user)
        ->postJson("/api/v1/comments/item/{$this->item->id}", ['body' => 'Public question'])
        ->json('id');

    Comment::factory()->internal()->create([
        'commentable_type' => $this->item->getMorphClass(),
        'commentable_id'   => $this->item->id,
        'parent_id'        => $parent,
        'body'             => 'Staff-only reply',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/comments/item/{$this->item->id}")
        ->assertJsonCount(1, 'comments')
        ->assertJsonPath('comments.0.replies', []);
});
