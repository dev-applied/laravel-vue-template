<?php

declare(strict_types=1);

namespace Modules\AuditLog;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\AuditLog\Console\Commands\PruneAuditLogCommand;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneAuditLogCommand::class]);
        }
    }
}
