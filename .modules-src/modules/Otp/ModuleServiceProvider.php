<?php

declare(strict_types=1);

namespace Modules\Otp;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Otp\Channels\EmailOtpChannel;
use Modules\Otp\Console\Commands\PruneOtpCodesCommand;
use Modules\Otp\Http\Middleware\RequiresStepUp;
use Modules\Otp\Support\ChannelRegistry;
use Modules\Otp\Support\OtpManager;
use Modules\Otp\Support\StepUpStore;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelRegistry::class);
        $this->app->singleton(OtpManager::class);
        $this->app->singleton(StepUpStore::class);

        // The one channel this module ships. A project adds SMS by binding
        // `otp.channel.sms` — nothing about a vendor lives in here.
        $this->app->bind('otp.channel.email', EmailOtpChannel::class);

        config()->set('otp', array_merge([
            'ttl_minutes'         => (int) env('OTP_TTL_MINUTES', 10),
            'length'              => (int) env('OTP_LENGTH', 6),
            'max_attempts'        => (int) env('OTP_MAX_ATTEMPTS', 5),
            'max_per_hour'        => (int) env('OTP_MAX_PER_HOUR', 5),
            'max_per_hour_per_ip' => (int) env('OTP_MAX_PER_HOUR_PER_IP', 20),
            'step_up_minutes'     => (int) env('OTP_STEP_UP_MINUTES', 15),
            // Never set in production; OtpManager refuses it there regardless.
            'qa_bypass_code' => env('OTP_QA_BYPASS_CODE'),
        ], (array) config('otp', [])));
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'otp');

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/Routes/api.php');

        // Step-up only exists in the login+step-up variant; the login choice
        // drops the controller and this file with it.
        if (file_exists(__DIR__.'/Routes/step-up.php')) {
            $router->aliasMiddleware('otp.step-up', RequiresStepUp::class);

            Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/Routes/step-up.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PruneOtpCodesCommand::class]);
        }
    }
}
