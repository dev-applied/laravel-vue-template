# Users

User administration: list, search, create, edit, deactivate, reactivate and delete —
with the guards that stop an admin locking everyone out of the application.

Extracted from the template kernel, which shipped a `UserController` with only
`index()` implemented (`store`/`show`/`update`/`destroy` were empty stubs) and no
management screen at all.

## What this module is NOT

It is **not** the user lookup. The kernel keeps `GET /api/v1/users` — a read-only
typeahead returning `{id, full_name, email}`, gated on authentication alone, which
is what every `AppAutoComplete endpoint="users"` binds to.

That split is deliberate. Ownership pickers are used by ordinary users, so the
lookup cannot sit behind `manage-users`; and the management list returns far more
per row, so it cannot be the thing everyone hits. Two audiences, two paths:

| Surface | Path | Gate | Returns |
|---|---|---|---|
| Lookup (kernel) | `GET /api/v1/users` | `auth:sanctum` | `id`, `full_name`, `email` |
| Management (this module) | `/api/v1/manage/users` | `auth:sanctum` + `can:manage-users` | full record |

The User **model** also stays in the kernel — Auth depends on it.

## Install

```sh
php artisan module:add Users
php artisan migrate          # adds users.deactivated_at
php artisan route:clear && composer typescript
```

No options, no composer dependencies.

### Define the gate

Every route is behind `can:manage-users`. Nothing in the module defines it, because
who counts as an administrator is a project decision. In `AppServiceProvider::boot()`:

```php
Gate::define('manage-users', fn (User $user) => $user->is_admin);
```

With RolesPermissions installed, bind it to a permission instead:

```php
Gate::define('manage-users', fn (User $user) => $user->hasPermission('users.manage'));
```

### Optionally gate the page too

The Vue route ships **ungated**. The client-side permission check reads
`all_permissions` off the auth payload, and that key comes from the
RolesPermissions module — with it absent the list is empty, every check fails,
and a gated route would bounce *everyone* to the dashboard. Shipping it gated
would make this module broken out of the box on its own.

That is not a hole: the API is gated regardless, so an unauthorised visitor
reaches the screen and the table shows the 403. If you run RolesPermissions and
would rather the page never render at all, add the gate in
`modules/Users/resources/ts/routes.ts`:

```ts
RouteDesigner.route("/users", () => import(...), ROUTES.USERS)
  .addPermissionAny("users.manage")
```

## Behaviour worth knowing

**Deactivation is the default, deletion is separate.** Deactivating sets
`deactivated_at` and **revokes every Sanctum token the user holds**. Without that,
"deactivated" means nothing until their existing token expires — they stay signed
in and keep working. Deletion is a distinct, separately confirmed action.

**You cannot deactivate or delete yourself**, and **you cannot remove the last
active account**. Both are enforced in `Support/UserGuard.php` and return 422 with
a message. The UI hides the controls on your own row rather than offering them and
then refusing, but the server check is the one that counts.

**Creating without a password is the safe path.** Omit `password` and the account
is created with an unusable random hash — never null — and a `Password::sendResetLink`
goes out so they choose their own. A password typed by an admin is a password that
has been typed into a chat window.

**An empty password string is treated as absent.** A form that only edits a name
still posts `password=""`; `nullable` does not catch that, so the save would be
rejected for a password nobody typed. Normalised in `prepareForValidation()`, so
web, mobile and direct API calls all behave the same.

**Search is grouped.** `first_name` / `last_name` / `email` are OR'd inside a
closure. Chained ungrouped, the OR swallows every other constraint — most
importantly the deactivation filter, which is how a "hidden" account reappears the
moment someone types their name.

## Files

```
Http/Controllers/UserController.php    CRUD + deactivate/reactivate
Http/Requests/                         Store/Update, empty-password normalisation
Http/Resources/UserResource.php        camelCase; parses deactivated_at defensively
Support/UserGuard.php                  self / last-active protections
Support/ManagesUsers.php               optional User-model trait (isActive(), scopeActive())
Database/Migrations/                   adds users.deactivated_at, guarded by hasColumn
resources/ts/pages/UsersPage.vue       the management screen
resources/ts/components/UserFormDialog.vue
resources/ts/composables/useUsers.ts   list + lifecycle actions
```

`UserResource` parses `deactivated_at` by hand rather than relying on a cast: the
column is one this module adds to the *kernel's* users table, so it is absent from
the kernel model's `$casts` and arrives as a raw string. Calling a Carbon method on
it 500s the listing. Same reason `deactivated_at` is assigned directly instead of
mass-assigned — it is not in the kernel model's `$fillable`.

## Tests

26 feature tests, `modules/Users/Tests/Feature/UserManagementTest.php`.
