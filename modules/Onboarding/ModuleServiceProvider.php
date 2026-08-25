<?php

declare(strict_types=1);

namespace Modules\Onboarding;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Onboarding\Http\Middleware\RequireOnboarding;
use Modules\Onboarding\Support\OnboardingRegistry;
use Modules\Onboarding\Support\OnboardingState;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OnboardingRegistry::class);
        $this->app->singleton(OnboardingState::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        // Aliased, never pushed onto a global group. The project decides which
        // routes are gated; see RequireOnboarding for why a global gate locks
        // people out of the screen that would release them.
        if (class_exists(RequireOnboarding::class)) {
            $this->app->make(Router::class)->aliasMiddleware('onboarded', RequireOnboarding::class);
        }
    }
}
