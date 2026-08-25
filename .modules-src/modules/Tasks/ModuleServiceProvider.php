<?php

declare(strict_types=1);

namespace Modules\Tasks;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Tasks\Console\Commands\NotifyOverdueTasksCommand;
use Modules\Tasks\Support\NullTaskScope;
use Modules\Tasks\Support\StatusMachine;
use Modules\Tasks\Support\TaskScope;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StatusMachine::class);

        // Permissive by default — a shared board is the common shape, and the
        // destructive operations do not depend on this. See TaskScope.
        $this->app->bind(TaskScope::class, NullTaskScope::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        $this->registerFallbackGate();

        if ($this->app->runningInConsole()) {
            $this->commands([NotifyOverdueTasksCommand::class]);
        }
    }

    /**
     * `manage-tasks` is the override that lets someone act on a task they
     * neither created nor were assigned.
     *
     * If nobody has defined it, fall CLOSED. An open default would restore
     * exactly the hole this exists to close — every signed-in user able to
     * retitle and delete anyone's task — and it would do it silently. The log
     * line is here because a permission that denies everyone looks identical
     * to a broken install from the outside.
     */
    protected function registerFallbackGate(): void
    {
        if (Gate::has('manage-tasks')) {
            return;
        }

        Gate::define('manage-tasks', function (): bool {
            Log::warning(
                'modules/Tasks: no `manage-tasks` gate is defined, so nobody can edit or delete a task '
                .'they did not create or get assigned. Define it in AppServiceProvider::boot() — see modules/Tasks/README.md.'
            );

            return false;
        });
    }
}
