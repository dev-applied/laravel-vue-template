<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\RolesPermissions\Models\Permission;
use Modules\RolesPermissions\Support\Guard;

/** @extends Factory<Permission> */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name'       => $this->faker->unique()->slug(2).'.view',
            'guard_name' => Guard::forUsers(),
        ];
    }
}
