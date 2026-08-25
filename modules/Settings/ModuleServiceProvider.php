<?php

declare(strict_types=1);

namespace Modules\Settings;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Settings\Support\SettingRegistry;
use Modules\Settings\Support\SettingsManager;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingRegistry::class);
        // scoped(), not singleton(). Laravel resets scoped instances between
        // queue jobs and between Octane requests; a singleton's in-process memo
        // does not reset, so a long-lived worker kept serving the settings it
        // read when it booted. Cache::forget on write clears the SHARED cache
        // for every process, but it cannot reach into another process's
        // property — which is exactly what the memo was.
        $this->app->scoped(SettingsManager::class);

        // Required here rather than through composer's `files` autoload: a
        // module is copied in, not required, so it never gets a composer.json
        // entry of its own.
        require_once __DIR__.'/Support/helpers.php';
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');
    }
}
