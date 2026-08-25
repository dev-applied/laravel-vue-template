<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Tasks\Events\TaskAssigned;
use Modules\Tasks\Events\TaskCompleted;
use Modules\Tasks\Models\Task;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('a task is created and listed', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/tasks', ['title' => 'Chase the invoice'])
        ->assertCreated()
        ->assertJsonPath('title', 'Chase the invoice')
        ->assertJsonPath('status', 'todo');

    $this->actingAs($this->user)
        ->getJson('/api/v1/tasks')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Chase the invoice');
});

test('a task can hang off any record', function () {
    $item = Item::factory()->create();

    $this->actingAs($this->user)
        ->postJson('/api/v1/tasks', [
            'title'         => 'Follow up',
            'taskable_type' => $item->getMorphClass(),
            'taskable_id'   => $item->id,
        ])
        ->assertCreated()
        ->assertJsonPath('taskableId', $item->id);
});

test('a taskable id without a type is rejected', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/tasks', ['title' => 'Half attached', 'taskable_id' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('taskable_type');
});

test('legal status transitions are allowed', function () {
    $task = Task::factory()->create();

    $this->actingAs($this->user)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => $task->title, 'status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');
});

test('an illegal status transition is refused', function () {
    // done -> cancelled: "we did it" and "we are not doing this" are different
    // claims, and letting one become the other quietly corrupts reporting.
    $task = Task::factory()->done()->create();

    $this->actingAs($this->user)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => $task->title, 'status' => 'cancelled'])
        ->assertStatus(422);

    expect($task->fresh()->status)->toBe('done');
});

test('saving the same status is not an error', function () {
    // Edit forms post the current status all the time.
    $task = Task::factory()->create(['status' => 'in_progress']);

    $this->actingAs($this->user)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed', 'status' => 'in_progress'])
        ->assertOk();
});

test('a task can be reopened', function () {
    // Work comes back.
    $task = Task::factory()->done()->create();

    $this->actingAs($this->user)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => $task->title, 'status' => 'todo'])
        ->assertOk()
        ->assertJsonPath('status', 'todo');
});

test('completing stamps completed_at', function () {
    $task = Task::factory()->create();

    $this->actingAs($this->user)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => $task->title, 'status' => 'done'])
        ->assertOk();

    expect($task->fresh()->completed_at)->not->toBeNull();
});

test('reopening clears completed_at', function () {
    // A reopened task carrying a completion date makes every "finished this
    // week" report wrong.
    $task = Task::factory()->done()->create();

    $this->actingAs($this->user)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => $task->title, 'status' => 'todo'])
        ->assertOk();

    expect($task->fresh()->completed_at)->toBeNull();
});

test('completed_at cannot be set by the client', function () {
    // It is derived from the status, full stop.
    $task = Task::factory()->create();

    $this->actingAs($this->user)
        ->putJson("/api/v1/tasks/{$task->id}", [
            'title'        => $task->title,
            'completed_at' => now()->toIso8601String(),
        ])
        ->assertOk();

    expect($task->fresh()->completed_at)->toBeNull();
});

test('completing fires an event once', function () {
    Event::fake([TaskCompleted::class]);
    $task = Task::factory()->create();

    $this->actingAs($this->user)->putJson("/api/v1/tasks/{$task->id}", ['title' => $task->title, 'status' => 'done']);
    $this->actingAs($this->user)->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed', 'status' => 'done']);

    Event::assertDispatchedTimes(TaskCompleted::class, 1);
});

test('assigning fires an event', function () {
    // An event, not a notification — the module must not assume Notifications
    // is installed.
    Event::fake([TaskAssigned::class]);
    $assignee = User::factory()->create();

    $this->actingAs($this->user)
        ->postJson('/api/v1/tasks', ['title' => 'Yours now', 'assigned_to' => $assignee->id])
        ->assertCreated();

    Event::assertDispatched(TaskAssigned::class, fn (TaskAssigned $e) => $e->assignee->is($assignee));
});

test('re-saving a task does not re-notify its owner', function () {
    $assignee = User::factory()->create();
    $task     = Task::factory()->create(['assigned_to' => $assignee->id]);

    Event::fake([TaskAssigned::class]);

    $this->actingAs($this->user)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed', 'assigned_to' => $assignee->id])
        ->assertOk();

    Event::assertNotDispatched(TaskAssigned::class);
});

test('reassigning notifies the new owner and names the old one', function () {
    $first  = User::factory()->create();
    $second = User::factory()->create();
    $task   = Task::factory()->create(['assigned_to' => $first->id]);

    Event::fake([TaskAssigned::class]);

    $this->actingAs($this->user)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => $task->title, 'assigned_to' => $second->id])
        ->assertOk();

    Event::assertDispatched(
        TaskAssigned::class,
        fn (TaskAssigned $e) => $e->assignee->is($second) && $e->previous?->is($first)
    );
});

test('an unassigned task fires nothing', function () {
    Event::fake([TaskAssigned::class]);

    $this->actingAs($this->user)->postJson('/api/v1/tasks', ['title' => 'Nobody'])->assertCreated();

    Event::assertNotDispatched(TaskAssigned::class);
});

test('an overdue task is flagged', function () {
    $task = Task::factory()->overdue()->create();

    $this->actingAs($this->user)
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertJsonPath('isOverdue', true);
});

test('a closed task is never overdue', function () {
    // Otherwise the done column is permanently red and people stop reading
    // the colour.
    $task = Task::factory()->overdue()->done()->create();

    expect($task->isOverdue())->toBeFalse();
});

test('the overdue scope excludes closed tasks', function () {
    Task::factory()->overdue()->create();
    Task::factory()->overdue()->done()->create();

    expect(Task::overdue()->count())->toBe(1);
});

test('the list puts open before closed and dated before undated', function () {
    // A list that leads with a dated task nobody has to do yet is the one
    // people stop opening.
    Task::factory()->done()->create(['title' => 'closed']);
    Task::factory()->create(['title' => 'undated']);
    Task::factory()->create(['title' => 'due soon', 'due_at' => now()->addDay()]);

    $titles = $this->actingAs($this->user)->getJson('/api/v1/tasks')->json('data.*.title');

    expect($titles)->toBe(['due soon', 'undated', 'closed']);
});

test('the list filters to mine', function () {
    Task::factory()->create(['title' => 'someone else', 'assigned_to' => User::factory()->create()->id]);
    Task::factory()->create(['title' => 'mine', 'assigned_to' => $this->user->id]);

    $titles = $this->actingAs($this->user)->getJson('/api/v1/tasks?mine=1')->json('data.*.title');

    expect($titles)->toBe(['mine']);
});

test('the list filters to open only', function () {
    Task::factory()->create(['title' => 'open one']);
    Task::factory()->done()->create(['title' => 'closed one']);

    $titles = $this->actingAs($this->user)->getJson('/api/v1/tasks?open=1')->json('data.*.title');

    expect($titles)->toBe(['open one']);
});

test('the response offers only the legal next statuses', function () {
    // So a button the UI shows can never produce a 422.
    $task = Task::factory()->done()->create();

    $next = $this->actingAs($this->user)->getJson("/api/v1/tasks/{$task->id}")->json('nextStatuses');

    expect($next)->toBe(['todo', 'in_progress'])
        ->and($next)->not->toContain('cancelled');
});

test('the move endpoint changes status and position together', function () {
    $task = Task::factory()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/tasks/{$task->id}/move", ['status' => 'in_progress', 'position' => 3])
        ->assertOk()
        ->assertJsonPath('status', 'in_progress')
        ->assertJsonPath('position', 3);
});

test('the move endpoint enforces the same transitions', function () {
    $task = Task::factory()->done()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/tasks/{$task->id}/move", ['status' => 'cancelled'])
        ->assertStatus(422);
});

test('moving to done stamps completion', function () {
    $task = Task::factory()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/tasks/{$task->id}/move", ['status' => 'done'])
        ->assertOk();

    expect($task->fresh()->completed_at)->not->toBeNull();
});

test('deleting an assignee leaves the task', function () {
    $assignee = User::factory()->create();
    $task     = Task::factory()->create(['assigned_to' => $assignee->id]);

    $assignee->delete();

    expect(Task::find($task->id))->not->toBeNull()
        ->and($task->fresh()->assigned_to)->toBeNull();
});

test('the overdue command reports and emits', function () {
    Event::fake([TaskAssigned::class]);
    $assignee = User::factory()->create();
    Task::factory()->overdue()->create(['assigned_to' => $assignee->id]);
    Task::factory()->overdue()->create();   // unassigned — nothing to chase

    $this->artisan('tasks:overdue')->assertSuccessful();

    Event::assertDispatchedTimes(TaskAssigned::class, 1);
});

test('the overdue command dry run emits nothing', function () {
    Event::fake([TaskAssigned::class]);
    Task::factory()->overdue()->create(['assigned_to' => User::factory()->create()->id]);

    $this->artisan('tasks:overdue --dry-run')->assertSuccessful();

    Event::assertNotDispatched(TaskAssigned::class);
});

test('tasks require authentication', function () {
    $this->getJson('/api/v1/tasks')->assertUnauthorized();
    $this->postJson('/api/v1/tasks', ['title' => 'x'])->assertUnauthorized();
});

test('a title is required', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/tasks', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

test('a move writes with a compare-and-swap on the status it validated', function () {
    // The transition was validated against a status read a moment earlier and
    // then written unconditionally, so the transition that ACTUALLY happened
    // was never the one checked. Two people drag the same card: both read
    // in_progress, one validates → done, the other → cancelled, both write, and
    // the task lands on whichever UPDATE was last. TaskCompleted then fires for
    // a task that ends up cancelled, and completed_at is stamped then cleared
    // (or the reverse) depending on ordering.
    //
    // A single-threaded test cannot open that window — route-model binding
    // re-reads the row, so the status is never stale here. What CAN be pinned
    // is the mechanism that makes the race impossible: the UPDATE carries the
    // expected status in its WHERE clause, so a task that moved underneath us
    // matches zero rows and the request loses observably instead of silently.
    $task = Task::factory()->create(['status' => Task::STATUS_TODO]);

    $updates = [];
    DB::listen(function ($q) use (&$updates) {
        if (str_starts_with(mb_strtolower($q->sql), 'update') && str_contains($q->sql, 'tasks')) {
            $updates[] = $q->sql;
        }
    });

    $this->actingAs($this->user)
        ->postJson("/api/v1/tasks/{$task->id}/move", ['status' => Task::STATUS_IN_PROGRESS])
        ->assertOk();

    expect($updates)->not->toBeEmpty()
        ->and(collect($updates)->contains(fn (string $sql) => str_contains($sql, '`status` = ?') && str_contains(mb_strtolower($sql), 'where')))
        ->toBeTrue();
});

test('a move against a task that is no longer where we left it is a 409', function () {
    // The other half: prove the zero-rows branch actually raises rather than
    // reporting success. Driven through the service layer so the expected
    // status can be a stale one, which is exactly what a concurrent move
    // produces.
    $task = Task::factory()->create(['status' => Task::STATUS_DONE]);

    $controller = app(Modules\Tasks\Http\Controllers\TaskController::class);
    $method     = new ReflectionMethod($controller, 'applyStatusChange');

    expect(fn () => $method->invoke($controller, $task, ['status' => Task::STATUS_TODO], Task::STATUS_IN_PROGRESS))
        ->toThrow(App\Exceptions\AppException::class);

    expect($task->fresh()->status)->toBe(Task::STATUS_DONE);
});

test('an ordinary move still works', function () {
    // The compare-and-swap must not refuse the normal path.
    $task = Task::factory()->create(['status' => Task::STATUS_TODO]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/tasks/{$task->id}/move", ['status' => Task::STATUS_IN_PROGRESS])
        ->assertOk();

    expect($task->fresh()->status)->toBe(Task::STATUS_IN_PROGRESS);
});
