<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mixins\HasManyMixin;
use App\Mixins\VuetifyPaginateMixin;
use App\Mixins\WhoDidItMixin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use ReflectionException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $this->configureModels();

        $this->configureDatabase();

        $this->configureHttp();

        $this->configureMixins();

        $this->configureRateLimiting();

        $this->configurePasswords();
    }

    public function configureModels(): void
    {
        Model::automaticallyEagerLoadRelationships();
    }

    public function configureDatabase(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    public function configureHttp(): void
    {
        Http::preventStrayRequests($this->app->runningUnitTests());
    }

    /**
     * @throws ReflectionException
     */
    public function configureMixins(): void
    {
        HasMany::mixin(new HasManyMixin);
        Relation::mixin(new VuetifyPaginateMixin);
        Builder::mixin(new VuetifyPaginateMixin);
        QueryBuilder::mixin(new VuetifyPaginateMixin);
        Blueprint::mixin(new WhoDidItMixin);
    }

    public function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(1000)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function configurePasswords(): void
    {
        if (! $this->app->isProduction()) {
            Password::defaults();

            return;
        }

        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });
    }

    // To enable Import both classes install laravel-notification-channels/twilio and call the function from the boot method
    private function configureTwilio(): void
    {
        if (! $this->app->environment('production')) {
            $this->app->extend(TwilioService::class, function () {
                return new TwilioHogService(
                    config('twilio-notification-channel.account_sid'),
                    config('twilio-notification-channel.auth_token'),
                    config('twilio-notification-channel.mock_server_url'),
                );
            });
        }
    }

    // To enable Import both classes install aws/aws-sdk-php and laravel-notification-channels/aws-sns and call the function from the boot method
    private function configureSns(): void
    {
        if (! $this->app->environment('staging', 'production')) {
            $this->app->bind(SnsService::class, function () {
                return new SnsHogService(config('services.sns', []));
            });
        }
    }
}
