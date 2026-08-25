<?php

declare(strict_types=1);

namespace Modules\Announcements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Announcements\Models\Announcement;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title'                    => $this->faker->sentence(4),
            'body'                     => $this->faker->paragraph(),
            'level'                    => Announcement::LEVEL_INFO,
            'placement'                => Announcement::PLACEMENT_BANNER,
            'audience'                 => Announcement::AUDIENCE_EVERYONE,
            'dismissible'              => true,
            'requires_acknowledgement' => false,
            'published_at'             => now()->subMinute(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['starts_at' => now()->addDay()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['ends_at' => now()->subMinute()]);
    }

    public function modal(): static
    {
        return $this->state(fn () => ['placement' => Announcement::PLACEMENT_MODAL]);
    }

    public function mustAcknowledge(): static
    {
        return $this->state(fn () => [
            'placement'                => Announcement::PLACEMENT_MODAL,
            'requires_acknowledgement' => true,
            'dismissible'              => false,
        ]);
    }
}
