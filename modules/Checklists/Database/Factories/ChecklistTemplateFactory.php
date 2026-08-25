<?php

declare(strict_types=1);

namespace Modules\Checklists\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Checklists\Models\ChecklistTemplate;

/** @extends Factory<ChecklistTemplate> */
class ChecklistTemplateFactory extends Factory
{
    protected $model = ChecklistTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name'        => 'Pre-delivery inspection',
            'description' => null,
            'is_active'   => true,
        ];
    }

    public function archived(): static
    {
        return $this->state(['is_active' => false]);
    }
}
