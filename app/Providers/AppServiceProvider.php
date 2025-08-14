<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mixins\HasManyMixin;
use App\Mixins\VuetifyPaginateMixin;
use App\Mixins\WhoDidItMixin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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
        HasMany::mixin(new HasManyMixin);
        Relation::mixin(new VuetifyPaginateMixin);
        Builder::mixin(new VuetifyPaginateMixin);
        Blueprint::mixin(new WhoDidItMixin);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(1000)->by($request->user()?->id ?: $request->ip());
        });
    }
}
