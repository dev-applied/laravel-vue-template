<?php

declare(strict_types=1);

namespace Modules\Tags\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tags\Models\Tag;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => $name,
            'slug' => Tag::slugFor($name),
            'type' => null,
        ];
    }
}
