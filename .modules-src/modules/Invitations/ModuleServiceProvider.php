<?php

declare(strict_types=1);

namespace Modules\Invitations;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Invitations\Console\Commands\PruneInvitationsCommand;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerFallbackGate();

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'invitations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneInvitationsCommand::class]);
        }
    }

    /**
     * Managing invitations denies by default, and says so.
     *
     * The management routes carried `auth:sanctum` and nothing else, while the
     * controller's docblock claimed an `invitations.manage` permission that did
     * not exist anywhere in the codebase. Combined with an unvalidated `role`
     * string, that made this module a privilege-escalation ladder: any
     * authenticated user invites an address they control, names any role they
     * like, accepts their own invitation, and lands in a fresh account holding
     * it.
     *
     * Fail-closed, in the shape modules/Users uses.
     */
    protected function registerFallbackGate(): void
    {
        if (Gate::has('manage-invitations')) {
            return;
        }

        Gate::define('manage-invitations', function (): bool {
            Log::warning(
                'modules/Invitations: no `manage-invitations` gate is defined, so inviting is denied '
                .'to everyone. Define it in AppServiceProvider::boot() — see modules/Invitations/README.md.'
            );

            return false;
        });
    }
}
