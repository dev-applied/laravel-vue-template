<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\RolesPermissions\Models\Permission;

/** @mixin Permission */
class PermissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Permissions are conventionally dotted (`items.edit`); surfacing the
        // prefix lets the UI group the matrix by subject without parsing.
        [$group] = array_pad(explode('.', (string) $this->name, 2), 2, null);

        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'group' => $group,
        ];
    }
}
