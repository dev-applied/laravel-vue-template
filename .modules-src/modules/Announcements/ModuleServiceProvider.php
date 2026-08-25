<?php

declare(strict_types=1);

namespace Modules\Announcements;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Announcements\Support\AudienceResolver;
use Modules\Announcements\Support\EveryoneAudience;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Default binding. A project that needs role- or plan-based targeting
        // rebinds this; the module never assumes either exists.
        $this->app->bind(AudienceResolver::class, EveryoneAudience::class);

        config()->set('announcements.email', env('ANNOUNCEMENTS_EMAIL', false));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if (is_dir(__DIR__.'/resources/views')) {
            $this->loadViewsFrom(__DIR__.'/resources/views', 'announcements');
        }

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');
    }
}
