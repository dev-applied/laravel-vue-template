<?php

declare(strict_types=1);

namespace Modules\GlobalSearch;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\GlobalSearch\Support\SearchRegistry;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shared registry — a project declares its searchable sources against
        // this from its own AppServiceProvider::boot().
        $this->app->singleton(SearchRegistry::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        // The `history=none` install removes the migration, the model, the
        // controller, its routes and its test together. Guarding on the file
        // rather than on a config flag means the absent variant costs nothing
        // at boot and cannot half-register.
        if (is_dir(__DIR__.'/Database/Migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        }

        if (is_file(__DIR__.'/Routes/history.php')) {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(__DIR__.'/Routes/history.php');
        }
    }
}
