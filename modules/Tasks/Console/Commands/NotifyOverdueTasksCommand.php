<?php

declare(strict_types=1);

namespace Modules\Tasks\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tasks\Events\TaskAssigned;
use Modules\Tasks\Models\Task;

/**
 * Re-announces overdue tasks so a project can chase them.
 *
 * Emits the same TaskAssigned event rather than sending anything: what
 * "chasing" means — an email, a Slack post, a digest — is the project's call.
 */
class NotifyOverdueTasksCommand extends Command
{
    protected $signature = 'tasks:overdue {--dry-run : List them and emit nothing}';

    protected $description = 'Emit TaskAssigned for every overdue, assigned task';

    public function handle(): int
    {
        $tasks = Task::query()->overdue()->whereNotNull('assigned_to')->with('assignee')->get();

        foreach ($tasks as $task) {
            $this->line(sprintf('%s — due %s — %s', $task->title, $task->due_at->diffForHumans(), $task->assignee?->email ?? '?'));

            if (! $this->option('dry-run') && $task->assignee !== null) {
                TaskAssigned::dispatch($task, $task->assignee, $task->assignee);
            }
        }

        $this->info($tasks->count().' overdue task(s).');

        return self::SUCCESS;
    }
}
