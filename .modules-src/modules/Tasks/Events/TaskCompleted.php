<?php

declare(strict_types=1);

namespace Modules\Tasks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Tasks\Models\Task;

class TaskCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Task $task) {}
}
