# RolesPermissions

Role and permission access control over `spatie/laravel-permission`, wired to
the authorization surface the template kernel **already ships**.

The kernel's `middleware/Authorization.ts` reads route meta `permissions_all`,
`permissions_any` and `roles`, and calls `$auth.hasPermission()` — but nothing
was providing the data those read. This module supplies it.

## Install

1. `php artisan module:add RolesPermissions`
2. `php artisan migrate`
3. Put the trait on your user model:

```php
use Modules\RolesPermissions\Traits\HasAccessControl;

class User extends Authenticatable
{
    use HasAccessControl;   // spatie's HasRoles + the accessors the frontend reads
}
```

4. `php artisan roles:grant-admin you@example.com`

Step 4 solves the bootstrap problem: role management is itself gated by
`roles.manage`, so a fresh install has nobody who can grant anything. The
command seeds the baseline role and permission if they are missing.

## What the trait adds

Beyond spatie's `HasRoles`, it **appends** two attributes:

| Attribute | Read by |
|---|---|
| `all_permissions` | `$auth.hasPermission` / `hasAnyPermissions` / `hasAllPermissions` |
| `role` | `middleware/Authorization.ts` for route meta `roles` |

They must be *appended*, not merely accessible: `AuthUserResource` serialises
with `$this->resource->toArray()`, so an un-appended accessor never reaches the
payload and every permission check silently fails closed.

## Gate a route

```ts
RouteDesigner.route("/items", "items/ItemListPage", ROUTES.ITEMS_LIST)
  .addPermissionAny("items.view")
```

And on the backend: `->middleware('permission:items.view')`. The `role`,
`permission` and `role_or_permission` aliases are registered by this module.

## Defining permissions

Permissions come from **your application** — a seeder or migration — not from
the UI. The permission endpoint is read-only on purpose: an app cannot check a
permission nobody wrote code for, so creating them at runtime only produces
dead rows. The UI groups them by dotted prefix (`items.edit` → group `items`).

## Guards

Never hardcode `'web'`. This template registers a `sanctum` guard, and spatie
infers a model's guard from the auth providers — so a role created as `web` is
invisible to a user resolved as `sanctum`. Use `Guard::forUsers()`, which
derives it from `auth.providers.users.model`.

For the same reason the module overrides `Role::users()`: spatie resolves that
relation's model from the role's guard, and Sanctum's guard ships with
`provider => null`, which makes `morphedByMany(null)` fatal.
