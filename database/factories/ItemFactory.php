<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ItemStatus;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status'      => fake()->randomElement(ItemStatus::cases()),
            'priority'    => fake()->numberBetween(1, 5),
            'due_date'    => fake()->optional(0.6)->dateTimeBetween('now', '+30 days'),
            // owner_id is left null by default; tests/seeders pass a User explicitly.
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => ItemStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ItemStatus::Archived]);
    }

    public function ownedBy(int $userId): static
    {
        return $this->state(fn () => ['owner_id' => $userId]);
    }
}
