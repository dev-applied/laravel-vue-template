<?php

declare(strict_types=1);

namespace Modules\Tags;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Tags\Console\Commands\MergeTagsCommand;
use Modules\Tags\Support\TaggableRegistry;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Permissive by default — see TagPoolScope::allows(). Bind your own
        // the moment a tag type is privileged.
        $this->app->bind(Support\TagPoolScope::class, Support\NullTagPoolScope::class);

        $this->app->singleton(TaggableRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([MergeTagsCommand::class]);
        }
    }
}
