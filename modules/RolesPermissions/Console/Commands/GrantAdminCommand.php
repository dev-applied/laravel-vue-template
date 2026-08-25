<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Console\Commands;

use Illuminate\Console\Command;
use Modules\RolesPermissions\Database\Seeders\RolesPermissionsSeeder;
use Modules\RolesPermissions\Models\Role;

/**
 * The bootstrap problem: role management is gated by `roles.manage`, so a fresh
 * install has nobody who can grant anything. This is the way in.
 */
class GrantAdminCommand extends Command
{
    protected $signature = 'roles:grant-admin {email : The email of the user to make an admin}';

    protected $description = 'Give a user the admin role, seeding the baseline role and permission if needed';

    public function handle(): int
    {
        // Resolve the model the auth provider is actually configured with
        // rather than hardcoding App\Models\User — that is the class spatie
        // infers guards from, and a project is free to point it elsewhere.
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = config('auth.providers.users.model');

        $user = $model::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error("No user with email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        if (! method_exists($user, 'assignRole')) {
            $this->components->error($model.' is missing the HasAccessControl trait — see modules/RolesPermissions/README.md.');

            return self::FAILURE;
        }

        if (! Role::query()->where('name', 'admin')->exists()) {
            (new RolesPermissionsSeeder)->run();
            $this->components->info('Seeded the admin role and roles.manage permission.');
        }

        $user->assignRole('admin');
        $this->components->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
