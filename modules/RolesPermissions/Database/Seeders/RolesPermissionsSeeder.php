<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\RolesPermissions\Models\Permission;
use Modules\RolesPermissions\Models\Role;
use Modules\RolesPermissions\Support\Guard;

/**
 * Bootstraps the one permission this module's own screens require. Without it
 * nobody can open role management, including the person installing the module —
 * so it seeds itself rather than assuming a project already has RBAC.
 *
 *   php artisan db:seed --class=\\Modules\\RolesPermissions\\Database\\Seeders\\RolesPermissionsSeeder
 */
class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $guard  = Guard::forUsers();
        $manage = Permission::findOrCreate('roles.manage', $guard);

        Role::findOrCreate('admin', $guard)->givePermissionTo($manage);
    }
}
