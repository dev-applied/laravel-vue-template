# Tasks

Assignable tasks with due dates and guarded status transitions, optionally
attached to any record.

Follow-ups and to-dos hung off a record recur across client repos, always
re-implemented — usually with a free-text status column that ends up holding
"Done", "done" and "complete", so every report grouping on it is wrong.

## What it gives you

| Piece | What it does |
|---|---|
| `HasTasks` trait | Attach tasks to any model. |
| `StatusMachine` | Which transitions are legal, and the API tells the UI. |
| `TaskAssigned` / `TaskCompleted` | Events, not notifications. |
| `TasksPage` | Filterable list with inline add. |
| `TaskBoardPage` | Kanban with drag-and-drop (option). |
| `tasks:overdue` | Re-announces overdue tasks so a project can chase them. |

## Install

```sh
php artisan module:add Tasks
php artisan migrate
```

**Option — `board`:**

| Choice | What you get |
|---|---|
| `list` (default) | The filterable list only. |
| `list+kanban` | Also a drag-and-drop board. |

## Attach tasks to a record

```php
use Modules\Tasks\Traits\HasTasks;

class Order extends Model
{
    use HasTasks;
}
```

```php
$order->tasks()->create(['title' => 'Chase the PO', 'due_at' => now()->addDays(3)]);
$order->openTasks;   // only the ones still live
```

## React to assignment

```php
Event::listen(TaskAssigned::class, function (TaskAssigned $event) {
    $event->assignee->notify(new TaskWasAssigned($event->task));
});
```

An event rather than a notification, for the same reason as Comments: this
module must not assume the Notifications module is installed, or that a project
wants an in-app badge rather than an email.

## Status transitions

```
todo         → in_progress, blocked, done, cancelled
in_progress  → todo, blocked, done, cancelled
blocked      → todo, in_progress, done, cancelled
done         → todo, in_progress            (reopen — work comes back)
cancelled    → todo
```

`done → cancelled` is deliberately absent. "We did it" and "we are not doing
this" are different claims, and letting one quietly become the other corrupts
reporting.

Every response carries `nextStatuses`, so the UI offers exactly the legal moves
and a visible button can never produce a 422. The board greys out columns a
dragged card cannot enter, for the same reason.

## Design decisions worth knowing

**Saving the same status is not an error.** Edit forms post the current status
all the time.

**`completed_at` is derived, never sent by the client.** Completing stamps it;
reopening clears it. A reopened task carrying a completion date makes every
"finished this week" report wrong.

**A closed task is never overdue,** however late it was finished. Otherwise the
done column is permanently red and people stop reading the colour.

**Re-saving a task does not re-notify its owner.** `TaskAssigned` fires only
when it actually changes hands, and `TaskCompleted` only on the transition.

**The list leads with open, dated tasks.** Closed last, undated after dated.
A list that opens on something nobody has to do yet is the one people stop
opening.

**Model defaults mirror the column defaults.** Eloquent does not read database
defaults back after an insert, so without `$attributes` a freshly created task
has a null status and anything reading it gets null — which is exactly how the
resource 500'd before the tests caught it.

**The board never uses `v-if` on a draggable subtree.** `v-show` throughout:
tearing out the element the pointer is over kills the drag mid-gesture. The
column grid stays mounted for the same reason.

**The drag is optimistic, then reconciled.** A card that waits for a round trip
feels broken; an illegal transition snaps it back.

**The board route is registered through `import.meta.glob`.** The `list` choice
deletes the page file, and a static dynamic-import path to a missing module
fails the whole build.

## Frontend

- `TasksPage.vue` — `ROUTES.TASKS` → `/tasks`.
- `TaskBoardPage.vue` — `ROUTES.TASK_BOARD` → `/tasks/board` (kanban option).
- `AppTaskCard` / `AppTaskColumn` — the board pieces.
- `useTasks(defaults?)` — `tasks`, `loading`, `loaded`, `overdueCount`,
  `byStatus(status)`, `fetch(params)`, `create()`, `update()`, `move()`,
  `remove()`. Pass `defaults` (e.g. `{taskable_type, taskable_id}`) to scope
  the whole composable to one record.
