<?php

declare(strict_types=1);

namespace Modules\Favorites;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Favorites\Support\FavoritableRegistry;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * The registry MUST be a singleton. A project registers its favouritable
     * types once at boot; if the container handed the controller a fresh
     * instance per resolution, that registration would be invisible and every
     * type would 404 — which is exactly what the tests saw first.
     */
    public function register(): void
    {
        $this->app->singleton(FavoritableRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        // Nothing is registered here on purpose. A module cannot know which of
        // a project's models should be favouritable, and guessing would be the
        // cross-module assumption the authoring rules warn about. The project
        // registers its own from AppServiceProvider:
        //
        //   FavoritableRegistry::register('article', Article::class);
    }
}
