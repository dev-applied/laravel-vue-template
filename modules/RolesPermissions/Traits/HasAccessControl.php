<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Traits;

use Spatie\Permission\Traits\HasRoles;

/**
 * Put this on App\Models\User:
 *
 *   class User extends Authenticatable
 *   {
 *       use \Modules\RolesPermissions\Traits\HasAccessControl;
 *   }
 *
 * It is spatie's HasRoles plus the two accessors the template's FRONTEND
 * already expects but nothing was providing:
 *
 *   all_permissions  — [{name}, …]; read by $auth.hasPermission /
 *                      hasAnyPermissions / hasAllPermissions
 *   role             — the first role name; read by middleware/Authorization.ts
 *                      for route meta `roles`
 *
 * They are appended (not just accessible) because AuthUserResource serialises
 * with $this->resource->toArray() — an accessor that is not appended never
 * reaches the payload, and every permission check silently fails closed.
 */
trait HasAccessControl
{
    use HasRoles;

    public function initializeHasAccessControl(): void
    {
        $this->appends = array_values(array_unique([
            ...$this->appends,
            'all_permissions',
            'role',
        ]));
    }

    /** @return array<int, array{id: int, name: string}> */
    public function getAllPermissionsAttribute(): array
    {
        return $this->getAllPermissions()
            ->map(fn ($permission): array => ['id' => $permission->id, 'name' => $permission->name])
            ->values()
            ->all();
    }

    /**
     * A single role name, because the kernel's route meta `roles` check is
     * `roles.includes($auth.user?.role)` — a scalar. Projects using several
     * roles per user should gate on permissions instead.
     */
    public function getRoleAttribute(): ?string
    {
        return $this->roles->first()?->name;
    }
}
