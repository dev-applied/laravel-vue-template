<?php

declare(strict_types=1);

namespace Modules\Tasks\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Tasks\Models\Task;

/**
 * Fired when a task changes hands, never when it is merely re-saved.
 *
 * An event rather than a notification: this module must not assume the
 * Notifications module is installed, or that a project wants an in-app badge
 * rather than an email. Same seam as Comments' UserMentioned.
 */
class TaskAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Task $task,
        public readonly User $assignee,
        public readonly ?User $previous = null,
    ) {}
}
