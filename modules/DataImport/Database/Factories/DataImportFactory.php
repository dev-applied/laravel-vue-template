<?php

declare(strict_types=1);

namespace Modules\DataImport\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\DataImport\Models\DataImport;

/** @extends Factory<DataImport> */
class DataImportFactory extends Factory
{
    protected $model = DataImport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'target'        => 'items',
            'original_name' => 'items.csv',
            'disk'          => config('filesystems.default'),
            'path'          => 'imports/items.csv',
            'status'        => DataImport::STATUS_UPLOADED,
        ];
    }
}
