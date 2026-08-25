<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\RolesPermissions\Models\Role;
use Modules\RolesPermissions\Support\Guard;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name'       => $this->faker->unique()->word(),
            'guard_name' => Guard::forUsers(),
        ];
    }
}
