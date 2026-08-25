<?php

declare(strict_types=1);

namespace Modules\Tasks\Support;

use Modules\Tasks\Models\Task;

/**
 * Which status changes are legal.
 *
 * A free-text status column drifts: something ends up "Done" and "done" and
 * "complete", and every report that groups on it is wrong. A transition table
 * also means "reopen" is a deliberate act rather than a side effect of an
 * edit form posting a stale value.
 */
class StatusMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        Task::STATUS_TODO        => [Task::STATUS_IN_PROGRESS, Task::STATUS_BLOCKED, Task::STATUS_DONE, Task::STATUS_CANCELLED],
        Task::STATUS_IN_PROGRESS => [Task::STATUS_TODO, Task::STATUS_BLOCKED, Task::STATUS_DONE, Task::STATUS_CANCELLED],
        Task::STATUS_BLOCKED     => [Task::STATUS_TODO, Task::STATUS_IN_PROGRESS, Task::STATUS_DONE, Task::STATUS_CANCELLED],
        // Reopening is allowed — work comes back. Going straight from done to
        // cancelled is not: cancel means "we are not doing this", which is a
        // different claim from "we did it and now we are undoing it".
        Task::STATUS_DONE      => [Task::STATUS_TODO, Task::STATUS_IN_PROGRESS],
        Task::STATUS_CANCELLED => [Task::STATUS_TODO],
    ];

    public function allows(?string $from, string $to): bool
    {
        if ($from === $to) {
            // A no-op save must not be an error — edit forms post the current
            // status all the time.
            return true;
        }

        return in_array($to, self::TRANSITIONS[$from ?? Task::STATUS_TODO] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public function nextFrom(?string $status): array
    {
        // Null-tolerant: a listing endpoint must not 500 over one row with a
        // bad status column.
        return self::TRANSITIONS[$status ?? Task::STATUS_TODO] ?? [];
    }
}
