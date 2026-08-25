<?php

declare(strict_types=1);

namespace Modules\Booking\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Booking\Models\BookableResource;
use Modules\Booking\Models\Booking;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'bookable_resource_id' => BookableResource::factory(),
            'name'                 => $this->faker->name(),
            'email'                => $this->faker->safeEmail(),
            'starts_at'            => now()->addDay()->startOfHour(),
            'ends_at'              => now()->addDay()->startOfHour()->addMinutes(30),
            'status'               => Booking::STATUS_CONFIRMED,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'       => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
