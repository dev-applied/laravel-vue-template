<?php

declare(strict_types=1);

namespace Modules\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Support\Console\Commands\PruneSpamCommand;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerFallbackGate();

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'support');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        // Threaded replies only exist in the ticketing variant; the contact
        // choice drops the controller and this file with it.
        if (file_exists(__DIR__.'/Routes/ticketing.php')) {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(__DIR__.'/Routes/ticketing.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PruneSpamCommand::class]);
        }
    }

    /**
     * Reading and answering tickets denies by default.
     *
     * The staff routes carried `auth:sanctum` alone, so every authenticated user
     * could read the whole queue — and a support form is where customers paste
     * passwords, order numbers and account details as a matter of routine. The
     * reply endpoint was worse: it mails an arbitrary body to the ticket's
     * address from our domain, into a thread the customer already trusts, which
     * is a phishing relay with our sending reputation behind it.
     *
     * Fail-closed, in the shape modules/Users uses.
     */
    protected function registerFallbackGate(): void
    {
        if (Gate::has('manage-support')) {
            return;
        }

        Gate::define('manage-support', function (): bool {
            Log::warning(
                'modules/Support: no `manage-support` gate is defined, so the ticket queue is denied '
                .'to everyone. Define it in AppServiceProvider::boot() — see modules/Support/README.md.'
            );

            return false;
        });
    }
}
