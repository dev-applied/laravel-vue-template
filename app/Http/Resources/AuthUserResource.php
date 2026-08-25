<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user, and any user rendered as a relation (an item's owner, an
 * assignee, a comment author).
 *
 * An explicit ALLOW-LIST, not `$this->resource->toArray()`.
 *
 * The previous version returned the whole model, so the payload silently
 * widened every time anything added a column to `users` — installing the Users
 * module already put `deactivated_at` in front of every client, and a module
 * adding an SSO subject, a recovery code or an internal score would have shipped
 * that too, with nobody deciding to. `$hidden` only covers `password` and
 * `remember_token`; it is a denylist, and a denylist cannot cover columns that
 * do not exist yet.
 *
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            // Accessor, and what every AppAutoComplete bound to a user renders.
            'full_name' => $this->full_name,
            'email'     => $this->email,

            // Both are contributed by the RolesPermissions module; a project
            // without it has neither, and the client already treats their
            // absence as "no permissions" and denies.
            //
            // Neither check may trigger a load. `isset($user->roles)` would:
            // it routes through getAttribute(), which lazy-loads the relation —
            // an N+1 across any list that serializes an owner per row. The
            // relation is included only when the caller eager-loaded it, and the
            // appended attribute only when the model actually declares it.
            'roles'           => $this->whenLoaded('roles'),
            'all_permissions' => $this->when(
                in_array('all_permissions', $this->resource->getAppends(), true),
                fn () => $this->resource->all_permissions
            ),
        ];
    }
}
