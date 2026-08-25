<?php

declare(strict_types=1);

namespace Modules\Booking;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Booking\Support\AvailabilityCalculator;
use Modules\Booking\Support\BookingService;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AvailabilityCalculator::class);
        $this->app->singleton(BookingService::class);

        config()->set('booking.requires_approval', env('BOOKING_REQUIRES_APPROVAL', false));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');
    }
}
