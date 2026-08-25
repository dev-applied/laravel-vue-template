<?php

declare(strict_types=1);

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Support\Models\SupportTicket;

/** @extends Factory<SupportTicket> */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name'     => $this->faker->name(),
            'email'    => $this->faker->safeEmail(),
            'subject'  => $this->faker->sentence(4),
            'body'     => $this->faker->paragraph(),
            'status'   => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
        ];
    }

    public function spam(): static
    {
        return $this->state(fn (): array => ['is_spam' => true]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicket::STATUS_RESOLVED, 'resolved_at' => now(),
        ]);
    }
}
