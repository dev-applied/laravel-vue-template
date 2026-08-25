<?php

declare(strict_types=1);

namespace Modules\Tasks\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Tasks\Models\Task;

/**
 * Add to any model that should carry follow-up tasks.
 */
trait HasTasks
{
    /** @return MorphMany<Task, self> */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    /** @return MorphMany<Task, self> */
    public function openTasks(): MorphMany
    {
        return $this->tasks()->open();
    }
}
