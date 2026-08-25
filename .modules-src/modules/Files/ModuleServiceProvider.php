<?php

declare(strict_types=1);

namespace Modules\Files;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Files\Support\FileAccess;
use Modules\Files\Support\OwnerFileAccess;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // register(), not boot(): bound in boot() this resolves to a fresh
        // instance per resolve, so a project's own binding in AppServiceProvider
        // could be silently replaced depending on provider order.
        $this->app->singleton(FileAccess::class, OwnerFileAccess::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        // Presigned-upload routes only exist when the module was installed with
        // storage=s3-presigned; the `local` choice drops this file entirely.
        if (file_exists(__DIR__.'/Routes/s3.php')) {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(__DIR__.'/Routes/s3.php');
        }
    }
}
