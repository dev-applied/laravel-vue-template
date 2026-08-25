<?php

declare(strict_types=1);

namespace Modules\Files\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Files\Models\File;

/** @extends Factory<File> */
class FileFactory extends Factory
{
    protected $model = File::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = $this->faker->slug(2).'.jpg';
        $path = 'uploads/'.pathinfo($name, PATHINFO_FILENAME).'/'.$name;

        return [
            'name'             => $name,
            'path'             => $path,
            'type'             => 'image/jpeg',
            'size'             => $this->faker->numberBetween(10, 5000),
            'disk'             => config('filesystems.default'),
            'responsive_paths' => ['original' => $path],
            'processed'        => true,
        ];
    }

    /** A row reserved by a presigned upload that has not been processed yet. */
    public function unprocessed(): static
    {
        return $this->state(fn (): array => ['processed' => false, 'size' => 0]);
    }
}
