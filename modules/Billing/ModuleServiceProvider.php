<?php

declare(strict_types=1);

namespace Modules\Billing;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Billing\Console\Commands\AssertBillingSafeCommand;
use Modules\Billing\Http\Middleware\RequiresTier;
use Modules\Billing\Services\EntitlementWriter;
use Modules\Billing\Services\RevenueCatEventMapper;
use Modules\Billing\Services\TransferResolver;
use Modules\Billing\Support\Entitlements;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RevenueCatEventMapper::class);
        $this->app->singleton(EntitlementWriter::class);
        $this->app->singleton(TransferResolver::class);
        $this->app->singleton(Entitlements::class);

        config()->set('billing', array_merge([
            'webhook_secret' => env('REVENUECAT_WEBHOOK_SECRET'),
            'secret_api_key' => env('REVENUECAT_SECRET_API_KEY'),
            'api_base'       => env('REVENUECAT_API_BASE', 'https://api.revenuecat.com/v1'),
            'management_url' => env('BILLING_MANAGEMENT_URL'),
            // Both default to FALSE. A permissive default here is the whole
            // category of bug this module is trying to avoid.
            'allow_sandbox'  => (bool) env('BILLING_ALLOW_SANDBOX', false),
            'allow_switcher' => (bool) env('BILLING_ALLOW_SWITCHER', false),
        ], (array) config('billing', [])));
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $router->aliasMiddleware('tier', RequiresTier::class);

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/Routes/api.php');

        // Only present in the switcher variant.
        if (file_exists(__DIR__.'/Routes/qa.php')) {
            Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/Routes/qa.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AssertBillingSafeCommand::class]);
        }
    }
}
