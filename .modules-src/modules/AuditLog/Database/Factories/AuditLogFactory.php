<?php

declare(strict_types=1);

namespace Modules\AuditLog\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\AuditLog\Models\AuditLog;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'auditable_type' => User::class,
            'auditable_id'   => User::factory(),
            'event'          => AuditLog::EVENT_UPDATED,
            'old_values'     => ['first_name' => 'Ada'],
            'new_values'     => ['first_name' => 'Grace'],
        ];
    }
}
