<?php

declare(strict_types=1);

namespace Modules\Booking\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Booking\Models\BookableResource;

/**
 * @extends Factory<BookableResource>
 */
class BookableResourceFactory extends Factory
{
    protected $model = BookableResource::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
