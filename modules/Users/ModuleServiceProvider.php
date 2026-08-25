<?php

declare(strict_types=1);

namespace Modules\Users;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Users\Support\UserGuard;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserGuard::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        $this->registerFallbackGate();
    }

    /**
     * Who counts as an administrator is a project decision, so this module does
     * not define `manage-users` — the project does, in AppServiceProvider.
     *
     * If nobody has, fall CLOSED rather than open. An open default would hand
     * every signed-in user the ability to delete accounts, and it would do so
     * silently. The log line exists because a permission that denies everyone
     * looks identical to a broken install from the outside.
     */
    protected function registerFallbackGate(): void
    {
        if (Gate::has('manage-users')) {
            return;
        }

        Gate::define('manage-users', function (): bool {
            Log::warning(
                'modules/Users: no `manage-users` gate is defined, so user management is denied to everyone. '
                .'Define it in AppServiceProvider::boot() — see modules/Users/README.md.'
            );

            return false;
        });
    }
}
