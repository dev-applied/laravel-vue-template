<?php

declare(strict_types=1);

namespace Modules\SavedViews\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SavedViews\Models\SavedView;

/**
 * @extends Factory<SavedView>
 */
class SavedViewFactory extends Factory
{
    protected $model = SavedView::class;

    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'key'        => 'items.index',
            'name'       => $this->faker->unique()->words(2, true),
            'payload'    => ['filters' => ['status' => 'open'], 'sortBy' => [], 'itemsPerPage' => 25],
            'is_default' => false,
            'is_shared'  => false,
            'position'   => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function shared(): static
    {
        return $this->state(fn () => ['is_shared' => true]);
    }
}
