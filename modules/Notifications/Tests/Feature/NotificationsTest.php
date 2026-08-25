<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Notifications\Models\Notification;
use Modules\Notifications\Notifications\ExampleNotification;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('the feed returns only the authenticated user notifications', function () {
    Notification::factory()->count(3)->create([
        'notifiable_type' => User::class,
        'notifiable_id'   => $this->user->id,
    ]);
    Notification::factory()->count(2)->create();  // someone else's

    $this->actingAs($this->user)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('the feed can be filtered to unread only', function () {
    Notification::factory()->count(2)->create([
        'notifiable_type' => User::class, 'notifiable_id' => $this->user->id,
    ]);
    Notification::factory()->read()->create([
        'notifiable_type' => User::class, 'notifiable_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/notifications?unread=1')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('the unread count endpoint returns a count and not a payload', function () {
    Notification::factory()->count(4)->create([
        'notifiable_type' => User::class, 'notifiable_id' => $this->user->id,
    ]);
    Notification::factory()->read()->create([
        'notifiable_type' => User::class, 'notifiable_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertExactJson(['count' => 4]);
});

test('the unread-count route is not shadowed by the wildcard route', function () {
    // Regression guard: /notifications/{notification} must not swallow the
    // literal /notifications/unread-count segment.
    $this->actingAs($this->user)
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonStructure(['count']);
});

test('a notification can be marked read', function () {
    $notification = Notification::factory()->create([
        'notifiable_type' => User::class, 'notifiable_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('notification.readAt', fn ($v) => $v !== null);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('marking all read clears every unread row for that user only', function () {
    Notification::factory()->count(3)->create([
        'notifiable_type' => User::class, 'notifiable_id' => $this->user->id,
    ]);
    $other = Notification::factory()->create();

    $this->actingAs($this->user)
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertExactJson(['count' => 0]);

    expect(Notification::query()->unread()->forNotifiable(User::class, $this->user->id)->count())->toBe(0)
        ->and($other->fresh()->read_at)->toBeNull();
});

test('a user cannot touch another users notification', function () {
    $foreign = Notification::factory()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/notifications/{$foreign->id}/read")
        ->assertNotFound();

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/notifications/{$foreign->id}")
        ->assertNotFound();

    expect($foreign->fresh()->read_at)->toBeNull();
});

test('a notification can be dismissed', function () {
    $notification = Notification::factory()->create([
        'notifiable_type' => User::class, 'notifiable_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/notifications/{$notification->id}")
        ->assertNoContent();

    expect(Notification::query()->count())->toBe(0);
});

test('notification routes require authentication', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
});

test('the example notification writes the payload the resource expects', function () {
    $this->user->notify(new ExampleNotification('Report ready', 'Your export finished.', '/reports/1'));

    $this->actingAs($this->user)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Report ready')
        ->assertJsonPath('data.0.body', 'Your export finished.')
        ->assertJsonPath('data.0.url', '/reports/1')
        ->assertJsonPath('data.0.readAt', null);
});
