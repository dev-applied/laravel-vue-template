<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\RolesPermissions\Database\Factories\RoleFactory;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;

    /**
     * Spatie resolves this relation's model from the ROLE'S GUARD, via
     * config("auth.guards.{$guard}.provider"). Sanctum registers its guard with
     * `provider => null`, so on a sanctum-guarded role that lookup yields null
     * and morphedByMany() dies with "Class name must be a valid object or a
     * string" — which surfaces as a 500 on any endpoint touching users().
     *
     * Resolve the user model from the provider directly instead. Same query,
     * no dependence on a guard naming its provider.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            config('auth.providers.users.model'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.role_pivot_key') ?: 'role_id',
            config('permission.column_names.model_morph_key')
        );
    }

    protected static function newFactory(): Factory
    {
        return RoleFactory::new();
    }
}
