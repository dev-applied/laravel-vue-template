<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\RolesPermissions\Database\Factories\PermissionFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return PermissionFactory::new();
    }
}
