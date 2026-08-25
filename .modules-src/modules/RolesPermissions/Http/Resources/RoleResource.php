<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\RolesPermissions\Models\Role;

/** @mixin Role */
class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'guardName'   => $this->guard_name,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'usersCount'  => $this->whenCounted('users'),
            'createdAt'   => $this->created_at,
        ];
    }
}
