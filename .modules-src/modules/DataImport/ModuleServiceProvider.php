<?php

declare(strict_types=1);

namespace Modules\DataImport;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\DataImport\Support\ImportRegistry;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImportRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');
    }
}
