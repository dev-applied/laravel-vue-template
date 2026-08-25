<?php

declare(strict_types=1);

namespace Modules\Exports;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Exports\Support\ExportRegistry;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shared registry — a project declares its export sources against this
        // from its own AppServiceProvider::boot().
        $this->app->singleton(ExportRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');
    }
}
