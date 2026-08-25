<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Tasks\Events\TaskAssigned;
use Modules\Tasks\Events\TaskCompleted;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Support\TaskScope;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Sign in BEFORE the factories run. WhoDidIt stamps created_by_id from
    // Auth::id(), so a task built outside a request has no creator — and since
    // editing and deleting are now gated on being the creator, every such row
    // was un-editable by anyone. That is correct behaviour and a badly-set-up
    // test; the fix belongs here, not in the guard.
    $this->actingAs($this->user);
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
    // beforeEach signs in so the factories record a creator; this is the one
    // test that needs the opposite. actingAs() binds the guard for the whole
    // test, so clearing the resolved guards is what actually signs out.
    auth()->forgetGuards();

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

// ── Authorization ────────────────────────────────────────────────────────────
// Every route was bare `auth:sanctum` over a table with no owner column. Proven
// by driving it before it was fixed: a signed-in stranger listed, read,
// retitled and DELETED another user's task, and nothing in the suite noticed.

test('a stranger cannot retitle a task they did not create and are not assigned', function () {
    $task = Task::factory()->create(['title' => 'Board meeting prep']);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Hijacked', 'status' => $task->status])
        ->assertForbidden();

    expect($task->fresh()->title)->toBe('Board meeting prep');
});

test('a stranger cannot delete a task they did not create', function () {
    $task     = Task::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();

    expect(Task::find($task->id))->not->toBeNull();
});

test('the person a task is assigned to may edit it but not delete it', function () {
    // Being handed a job is permission to correct its description; it is not
    // permission to destroy the record of it.
    $assignee = User::factory()->create();
    $task     = Task::factory()->create(['assigned_to' => $assignee->id]);

    $this->actingAs($assignee)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Clarified', 'status' => $task->status])
        ->assertOk();

    $this->actingAs($assignee)->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();
});

test('manage-tasks overrides both, and is denied when nobody defines it', function () {
    $task     = Task::factory()->create();
    $stranger = User::factory()->create();

    // The fallback gate falls CLOSED. An open default would restore exactly the
    // hole this closes, silently.
    $this->actingAs($stranger)->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();

    Gate::define('manage-tasks', fn () => true);

    $this->actingAs($stranger)->deleteJson("/api/v1/tasks/{$task->id}")->assertOk();
});

test('anyone who can see a task may move it, because that is what a board is for', function () {
    // Deliberately NOT symmetrical with editing. Dragging someone else's card
    // into "in progress" is the point of a shared board, and move() changes
    // only status and position.
    $task     = Task::factory()->create(['status' => 'todo']);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson("/api/v1/tasks/{$task->id}/move", ['status' => 'in_progress'])
        ->assertOk();

    expect($task->fresh()->status)->toBe('in_progress');
});

test('a bound scope hides tasks, and hides them as a 404', function () {
    app()->bind(TaskScope::class, fn () => new class implements TaskScope
    {
        public function apply(Builder $query, mixed $user): void
        {
            $query->where('priority', 'high');
        }

        public function attributes(mixed $user): array
        {
            return [];
        }
    });

    $visible = Task::factory()->create(['priority' => 'high']);
    $hidden  = Task::factory()->create(['priority' => 'low']);

    $ids = $this->actingAs($this->user)->getJson('/api/v1/tasks')->assertOk()->json('data.*.id');

    expect($ids)->toContain($visible->id)->not->toContain($hidden->id);

    // 404 and not 403: once a project narrows the scope, the difference between
    // two status codes must not confirm that a task outside it exists.
    $this->actingAs($this->user)->getJson("/api/v1/tasks/{$hidden->id}")->assertNotFound();
    $this->actingAs($this->user)->deleteJson("/api/v1/tasks/{$hidden->id}")->assertNotFound();
});
