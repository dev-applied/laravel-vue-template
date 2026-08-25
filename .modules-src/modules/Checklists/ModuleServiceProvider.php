<?php

declare(strict_types=1);

namespace Modules\Checklists;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Checklists\Support\ChecklistSubjects;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shared allow-list — a project declares what is inspectable from its
        // own AppServiceProvider::boot().
        $this->app->singleton(ChecklistSubjects::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');
    }
}
