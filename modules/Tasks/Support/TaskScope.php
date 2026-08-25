<?php

declare(strict_types=1);

namespace Modules\Tasks\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * A seam for narrowing which tasks a request can SEE.
 *
 * Reads are separated from writes here on purpose. A task board is usually
 * collaborative — everyone can see the column, pick a card up, move it along —
 * so the shipped default keeps reads open and the module does not pretend to
 * know a project's answer. Writes are not symmetrical with that: deleting
 * somebody else's task is never collaboration, so it is governed by ownership
 * in the controller rather than by this, and no binding is required to be safe.
 *
 *   $this->app->bind(TaskScope::class, TeamScope::class);
 */
interface TaskScope
{
    /**
     * Narrow the task query — a team id, a tenant id, a project id.
     *
     * @param  Builder<\Modules\Tasks\Models\Task>  $query
     */
    public function apply(Builder $query, mixed $user): void;

    /**
     * Extra attributes stamped onto a task as it is created.
     *
     * @return array<string, mixed>
     */
    public function attributes(mixed $user): array;
}
