# SavedViews

Named filter sets for any listing screen. Save the filters you are looking at,
name them, pick one to open on, and optionally share it with the team.

The kernel already syncs filters to the URL (`useFilters`), which makes a view
shareable by copy-paste but not *reusable* — nobody keeps a bookmark for
"open, mine, high priority, newest first". This makes that a named thing.

### Screen access

`key` arrives from the request as a free string, and the module has no idea
which screens a project has. Without a check, a low-privilege user can guess
`admin.users.index`, `payroll.index`, `invoices.index` and read back every
**shared** view on those screens — payload and owner name included. A payload is
filters, sort and column choices, which in practice carry record ids, search
terms and internal status codes.

`SavedViewScope::allows($key, $user)` is where that is decided, on both `index`
and `store`. A refusal is a 404, so a privileged screen key is indistinguishable
from a nonexistent one.

```php
public function allows(string $key, mixed $user): bool
{
    return match (true) {
        str_starts_with($key, 'admin.') => $user?->can('admin.access') ?? false,
        default                         => true,
    };
}
```

The shipped `NullScope` allows everything, deliberately: a module-level default
that refused unknown keys would make saved views vanish on install for reasons
nobody could trace. **Implement it the moment any screen behind this is
privileged** — `store` matters as much as `index`, because a view marked
`is_shared` on a screen you cannot open is a row planted in the picker of
everyone who can.

## What it gives you

| Piece | What it does |
|---|---|
| `GET/POST/PUT/DELETE /saved-views` | Per-user views, scoped to a screen key. |
| `AppSavedViews` | A picker that clips onto an existing table. Registered globally. |
| Default view | Marked with a star; the screen opens on it. |
| Sharing | A view marked shared appears for everyone on that screen, read-only. |
| `SavedViewScope` | A tenancy seam — bind once, every query in the module is covered. |

## Install

```sh
php artisan module:add SavedViews
php artisan migrate
```

No options, no composer dependencies.

## Use it

```vue
<script lang="ts">
import {useFilters} from "@/composables/useFilters"

export default defineComponent({
  setup() {
    const {filters, reset} = useFilters({search: "", status: null, owner_id: null})
    return {filters, reset}
  },
  methods: {
    applyView(payload) {
      // null = "All records" was picked
      if (!payload) return this.reset()
      Object.assign(this.filters, payload.filters ?? {})
    },
  },
})
</script>

<template>
  <AppSavedViews
    view-key="items.index"
    :current="{filters}"
    @apply="applyView"
  />

  <AppPaginationTable endpoint="items" :filters="filters" />
</template>
```

`view-key` is the screen identifier. Views never cross keys, so `items.index`
and `orders.index` keep separate pickers even for the same person.

## The payload is yours

The module never reads inside `payload`. Whatever the screen hands to `save()`
comes back from `apply()` unchanged — filters, sort, visible columns, page
size, all of it. That is what lets one picker serve a table with three filters
and another with twelve.

The only constraint is a 64-key cap: a saved view is a filter set, not a place
to park a dataset.

## Multi-tenant projects

Bind a scope **before** you have real data, not after:

```php
use Modules\SavedViews\Support\SavedViewScope;

class TenantScope implements SavedViewScope
{
    public function apply(Builder $query, mixed $user): void
    {
        $query->where('tenant_id', $user->tenant_id);
    }

    public function attributes(mixed $user): array
    {
        return ['tenant_id' => $user->tenant_id];
    }
}

// A service provider's register():
$this->app->bind(SavedViewScope::class, TenantScope::class);
```

Sharing is the reason this matters. A per-user row is naturally isolated; a
row marked `is_shared` is visible to everyone on the same screen key, and
"everyone" has to mean "everyone in this tenant". A `tenant_id` column on
`saved_views` plus this binding covers the module in one place, instead of
every query being a separate thing to remember later.

## Design decisions worth knowing

**A shared view someone else owns is read-only.** Applying it is fine;
renaming or deleting it is not. One person tidying their own picker must not
silently rewrite everyone else's. The API returns `isOwn` so the UI omits the
edit controls entirely rather than offering them and then 403ing.

**Duplicate names are a 422, not a 500.** The unique index is the backstop;
validation is what tells the person which word to change. Uniqueness is per
person per screen — two people may each have a "Triage".

**One default per user per screen, enforced by clearing the others.** Not by a
unique index, which MySQL cannot do partially. Two defaults means the screen
opens on whichever row sorted first, which reads as random.

**Your views sort above shared ones.** Your own picker should lead with what
you made.

**The default applies on mount, opt-out.** Someone who set a default expects
the screen to open on it. Re-picking it every visit is what makes saved views
feel pointless.

**Deleting a user takes their views.** `cascadeOnDelete`, including views they
had shared — a shared view whose owner is gone has nobody who can maintain it.


### One thing the payload does NOT guarantee

`payload` is a MySQL `json` column, and MySQL's native JSON type is a normalised
binary format: it **sorts object keys** (by length, then lexicographically) and
drops duplicates. MariaDB's `JSON` is LONGTEXT plus a CHECK constraint and hands
back the literal string, so the two engines genuinely differ here.

JSON **array** order is preserved by both, so the parts of a view where order
carries meaning — a column list, a multi-sort — round trip exactly. Only the
order of keys within an object moves. Do not build a screen that depends on it.

## Frontend

- `AppSavedViews` — registered globally. Props: `viewKey` (required),
  `current` (required), `allowSharing`, `applyDefault`. Emits `apply` with the
  saved payload, or `null` for "All records".
- `useSavedViews(key)` — `views`, `mine`, `shared`, `defaultView`, `loading`,
  `loaded`, `fetch()`, `save(name, payload, opts)`, `update(view, changes)`,
  `remove(view)`. Use it directly for a bespoke picker.
