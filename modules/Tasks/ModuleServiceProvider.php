<?php

declare(strict_types=1);

namespace Modules\Tasks;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Tasks\Console\Commands\NotifyOverdueTasksCommand;
use Modules\Tasks\Support\StatusMachine;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StatusMachine::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([NotifyOverdueTasksCommand::class]);
        }
    }
}
