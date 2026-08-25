<?php

declare(strict_types=1);

namespace Modules\Notifications\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Notifications\Models\Notification;

/** @extends Factory<Notification> */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id'              => (string) Str::uuid(),
            'type'            => 'Modules\\Notifications\\Notifications\\ExampleNotification',
            'notifiable_type' => User::class,
            'notifiable_id'   => User::factory(),
            'data'            => [
                'title' => $this->faker->sentence(4),
                'body'  => $this->faker->sentence(8),
                'icon'  => 'info',
                'color' => 'primary',
                'url'   => null,
            ],
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}
