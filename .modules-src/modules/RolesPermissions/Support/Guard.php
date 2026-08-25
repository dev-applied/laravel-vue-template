<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Support;

use Spatie\Permission\Guard as SpatieGuard;

/**
 * The guard roles and permissions must be created under.
 *
 * Never hardcode 'web'. Spatie infers a model's guard by matching it against
 * the class each auth provider is configured with, and this template registers
 * a `sanctum` guard — so a role seeded as 'web' is invisible to a user spatie
 * resolves as 'sanctum', and every check fails with RoleDoesNotExist. Derive it
 * from the configured user model so the module works whatever a project's
 * guards look like.
 */
class Guard
{
    public static function forUsers(): string
    {
        /** @var class-string $model */
        $model = config('auth.providers.users.model');

        return SpatieGuard::getDefaultName($model);
    }
}
