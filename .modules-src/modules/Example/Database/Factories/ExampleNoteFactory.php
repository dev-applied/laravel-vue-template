<?php

declare(strict_types=1);

namespace Modules\Example\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Example\Models\ExampleNote;

/**
 * @extends Factory<ExampleNote>
 */
class ExampleNoteFactory extends Factory
{
    protected $model = ExampleNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'note' => fake()->sentence(),
        ];
    }
}
