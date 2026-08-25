<?php

declare(strict_types=1);

namespace Modules\Tasks\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Tasks\Events\TaskAssigned;
use Modules\Tasks\Events\TaskCompleted;
use Modules\Tasks\Http\Requests\StoreTaskRequest;
use Modules\Tasks\Http\Resources\TaskResource;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Support\StatusMachine;
use Modules\Tasks\Support\TaskScope;

class TaskController extends Controller
{
    public function __construct(
        private readonly StatusMachine $statuses,
        private readonly TaskScope $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tasks = Task::query()
            ->tap(fn ($query) => $this->scope->apply($query, $request->user()))
            ->with('assignee')
            ->filter($request->only(['status', 'priority', 'assigned_to', 'search', 'open', 'overdue']))
            ->when($request->boolean('mine'), fn ($q) => $q->assignedTo($request->user()))
            // Open first, then by due date with undated last, then priority.
            // A list that leads with a dated task nobody has to do yet is the
            // one people stop opening.
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END', [Task::STATUS_DONE, Task::STATUS_CANCELLED])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderBy('id')
            ->vuetifyPaginate();

        $tasks->setCollection(
            $tasks->getCollection()->map(fn (Task $t) => new TaskResource($t))->collect()
        );

        return response()->json($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::create($request->validated() + $this->scope->attributes($request->user()));

        $this->announceAssignment($task, null);

        return response()->json(new TaskResource($task->load('assignee')), 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->assertVisible($request, $task);

        return response()->json(new TaskResource($task->load('assignee')));
    }

    public function update(StoreTaskRequest $request, Task $task): JsonResponse
    {
        $this->assertMayEdit($request, $task);

        $previousAssignee = $task->assignee;
        $previousStatus   = $task->status;

        $data = $request->validated();

        if (isset($data['status']) && ! $this->statuses->allows($previousStatus, $data['status'])) {
            throw new AppException(
                "A task cannot go from {$previousStatus} to {$data['status']}.",
                422
            );
        }

        $data = $this->stampCompletion($data, $previousStatus);

        // Same compare-and-swap as move() when the status is part of the change;
        // a plain update() would write a transition nobody validated.
        if (isset($data['status'])) {
            $this->applyStatusChange($task, $data, $previousStatus);
        } else {
            $task->update($data);
        }

        if (($data['assigned_to'] ?? $task->assigned_to) !== $previousAssignee?->getKey()) {
            $this->announceAssignment($task->fresh(), $previousAssignee);
        }

        if ($task->status === Task::STATUS_DONE && $previousStatus !== Task::STATUS_DONE) {
            TaskCompleted::dispatch($task);
        }

        return response()->json(new TaskResource($task->fresh()->load('assignee')));
    }

    /**
     * The board's drag-and-drop endpoint: status and position in one call.
     */
    public function move(Request $request, Task $task): JsonResponse
    {
        // Moving a card is board collaboration — dragging someone else's task
        // into "in progress" is the point of a shared board, and it changes
        // only status and position. Anyone who can see the task may move it.
        $this->assertVisible($request, $task);

        $data = $request->validate([
            'status'   => ['required', 'string'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (! $this->statuses->allows($task->status, $data['status'])) {
            throw new AppException(
                "A task cannot go from {$task->status} to {$data['status']}.",
                422
            );
        }

        $previousStatus = $task->status;

        $this->applyStatusChange($task, $this->stampCompletion($data, $previousStatus), $previousStatus);

        if ($task->status === Task::STATUS_DONE && $previousStatus !== Task::STATUS_DONE) {
            TaskCompleted::dispatch($task);
        }

        return response()->json(new TaskResource($task->fresh()->load('assignee')));
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        // Stricter than editing: deleting someone else's task is never
        // collaboration, and there is no undo. The assignee does not qualify —
        // being given a job is not permission to destroy the record of it.
        $this->assertVisible($request, $task);

        if (! $this->isCreator($request, $task) && ! Gate::forUser($request->user())->allows('manage-tasks')) {
            throw new AppException('You cannot delete a task you did not create.', 403);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    /**
     * Apply a status change only if the task is still where we validated from.
     *
     * The transition was validated against a status read a moment earlier and
     * then written unconditionally, so the transition that ACTUALLY happened
     * was never the one checked. Two people drag the same card at once: both
     * read `in_progress`, one validates in_progress → done (allowed), the other
     * in_progress → cancelled (allowed), both write, and the task lands on
     * whichever UPDATE was last. The real transition — done → cancelled — was
     * never validated and may well be forbidden. `TaskCompleted` fires for a
     * task that ends up cancelled, and `completed_at` is stamped then cleared
     * or the reverse, depending on ordering.
     *
     * A compare-and-swap makes the loser observable instead of silent. This is
     * the module's headline feature — a shared drag-and-drop board — so two
     * people moving one card is the ordinary case, not an exotic one.
     *
     * @param  array<string, mixed>  $data
     */
    protected function applyStatusChange(Task $task, array $data, string $expectedStatus): void
    {
        $changed = Task::query()
            ->whereKey($task->getKey())
            ->where('status', $expectedStatus)
            ->update($data);

        if ($changed === 0) {
            throw new AppException(
                'Somebody else moved this task while you were working on it. Refresh and try again.',
                409
            );
        }

        $task->refresh();
    }

    /**
     * Every route used to be bare `auth:sanctum` over a table with no owner
     * column, so any signed-in user could list, read, retitle and delete every
     * task in the install. Verified by driving it, not inferred.
     *
     * Visibility is the scope's business — the shipped default keeps a board
     * shared, which is the common shape — so this is 404 rather than 403: once
     * a project narrows the scope, a task outside it must not be confirmed to
     * exist by the difference between two status codes.
     */
    private function assertVisible(Request $request, Task $task): void
    {
        $visible = Task::query()
            ->tap(fn ($query) => $this->scope->apply($query, $request->user()))
            ->whereKey($task->getKey())
            ->exists();

        if (! $visible) {
            throw new AppException('Task not found.', 404);
        }
    }

    /**
     * Editing: the creator, the person it is assigned to, or someone holding
     * `manage-tasks`. The assignee is included because being handed a task and
     * not being able to correct its description is the kind of friction that
     * makes people keep a private list instead.
     */
    private function assertMayEdit(Request $request, Task $task): void
    {
        $this->assertVisible($request, $task);

        $userId = (int) $request->user()->getKey();

        if ($this->isCreator($request, $task)
            || (int) $task->assigned_to === $userId
            || Gate::forUser($request->user())->allows('manage-tasks')) {
            return;
        }

        throw new AppException('You cannot change a task you did not create and are not assigned.', 403);
    }

    private function isCreator(Request $request, Task $task): bool
    {
        $column = $task->getCreatedByColumn();

        return $task->getAttribute($column) !== null
            && (int) $task->getAttribute($column) === (int) $request->user()->getKey();
    }

    /**
     * completed_at is derived from the status, never sent by the client.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stampCompletion(array $data, string $previousStatus): array
    {
        if (! isset($data['status'])) {
            return $data;
        }

        if ($data['status'] === Task::STATUS_DONE && $previousStatus !== Task::STATUS_DONE) {
            $data['completed_at'] = now();
        }

        // Reopening clears it. A reopened task carrying a completion date makes
        // every "finished this week" report wrong.
        if ($data['status'] !== Task::STATUS_DONE) {
            $data['completed_at'] = null;
        }

        return $data;
    }

    private function announceAssignment(Task $task, ?User $previous): void
    {
        if ($task->assigned_to === null) {
            return;
        }

        $assignee = $task->assignee ?? User::find($task->assigned_to);

        // Only when it actually changed hands — re-saving a task must not
        // re-notify the person who already owns it.
        if ($assignee !== null && $assignee->getKey() !== $previous?->getKey()) {
            TaskAssigned::dispatch($task, $assignee, $previous);
        }
    }
}
