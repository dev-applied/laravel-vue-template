<?php

declare(strict_types=1);

namespace Modules\Tasks\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tasks\Models\Task;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title'    => $this->faker->sentence(4),
            'status'   => Task::STATUS_TODO,
            'priority' => 'normal',
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['due_at' => now()->subDay()]);
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status'       => Task::STATUS_DONE,
            'completed_at' => now(),
        ]);
    }
}
