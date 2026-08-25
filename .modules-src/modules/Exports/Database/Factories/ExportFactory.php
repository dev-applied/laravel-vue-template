<?php

declare(strict_types=1);

namespace Modules\Exports\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Exports\Models\Export;

/** @extends Factory<Export> */
class ExportFactory extends Factory
{
    protected $model = Export::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source'  => 'items',
            'format'  => 'csv',
            'status'  => Export::STATUS_PENDING,
            'filters' => [],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status'       => Export::STATUS_COMPLETED,
            'disk'         => config('filesystems.default'),
            'path'         => 'exports/items-test.csv',
            'row_count'    => 3,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => Export::STATUS_FAILED,
            'error'  => 'Something went wrong.',
        ]);
    }
}
