<?php

declare(strict_types=1);

namespace Modules\SavedViews;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\SavedViews\Support\NullScope;
use Modules\SavedViews\Support\SavedViewScope;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Single-tenant by default. A multi-tenant project rebinds this once
        // and every query in the module is covered — see Support/SavedViewScope.
        $this->app->bind(SavedViewScope::class, NullScope::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');
    }
}
