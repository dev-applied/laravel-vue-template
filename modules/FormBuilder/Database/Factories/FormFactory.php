<?php

declare(strict_types=1);

namespace Modules\FormBuilder\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\FormBuilder\Models\Form;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'name'   => $name,
            'slug'   => Str::slug($name),
            'schema' => [
                ['key' => 'full_name', 'label' => 'Full name', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ],
            'is_published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }

    public function publicForm(): static
    {
        return $this->state(fn () => ['is_public' => true]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['closes_at' => now()->subDay()]);
    }
}
