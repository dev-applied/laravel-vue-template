# Tags

Polymorphic tags for any model, with a filter that plugs into the existing
listing stack.

Ad-hoc categorisation recurs across client repos, almost always as a
comma-separated string column — unfilterable, unnormalised, and full of the
same word three times with different capitalisation.

## What it gives you

| Piece | What it does |
|---|---|
| `HasTags` trait | Add to any model. |
| `TaggableRegistry` | The allow-list of what the API may tag. |
| `withAllTags` / `withAnyTags` | Query scopes that plug into an existing listing. |
| `AppTagInput` | Chips + autocomplete on a detail screen. Registered globally. |
| `AppTagFilter` | A multi-select for a listing screen. Emits slugs. |
| `tags:dedupe` | Folds legacy duplicates together on an existing database. |

## Install

```sh
php artisan module:add Tags
php artisan migrate
```

No options, no composer dependencies.

## Wire it up

```php
use Modules\Tags\Traits\HasTags;

class Order extends Model
{
    use HasTags;

    // Optional. Null (the default) means this model draws from the global
    // pool; returning a string gives it its own, so an "urgent" on orders can
    // be a different tag from an "urgent" on tickets.
    public function tagType(): ?string
    {
        return 'order';
    }
}
```

```php
// A service provider's boot():
$registry->register('order', Order::class, ability: 'update');
```

```php
// Who may rename, merge or delete tags — that changes what everyone sees.
Gate::define('manage-tags', fn ($user) => $user->isAdmin());
```

```vue
<!-- detail screen -->
<AppTagInput type="order" :record-id="order.id" tag-type="order" />

<!-- listing screen -->
<AppTagFilter v-model="filters.tags" tag-type="order" />
```

```php
// and the listing endpoint
$orders = Order::query()
    ->withAllTags($request->input('tags', []))
    ->vuetifyPaginate();
```

## Design decisions worth knowing

**The slug is the identity.** "Urgent", "urgent", " URGENT " and "Urgent!" are
one tag. Without that a tag list becomes near-duplicates and no filter on it is
trustworthy. It also means punctuation alone never creates a second tag — which
is usually what you want, and occasionally surprising.

**`withAllTags` narrows; it is the default.** "urgent" plus "billing" means the
overlap. An OR filter that *widens* the list as you add terms is the surprising
one. `withAnyTags` is there when you want the union.

**The filter emits slugs, not names.** A name-based filter breaks the moment
someone renames a tag.

**`usage_count` is counted, not incremented.** An increment drifts the first
time a record loses a tag or is deleted with tags on it, and a wrong count is
worse than none because the picker sorts on it.

**Attaching uses `syncWithoutDetaching`.** Plain `attach()` on a tag the record
already has violates the unique index and 500s an otherwise harmless action.

**Renaming onto an existing tag is refused, not merged silently.** That is a
merge request. Silently colliding would break the unique index or orphan every
record on one of them. `POST /tags/{tag}/merge` does it properly, with
`insertOrIgnore` so a record carrying *both* tags does not fire the unique
index halfway through and leave the merge half-done.

**The type is folded into the slug rather than kept as a unique pair.** MySQL
treats every NULL in a unique index as distinct, so a `(slug, type)` unique
index would let global tags duplicate freely.

**Reading a record's tags runs the same ability as writing them.** A tag list
can leak how a record has been categorised internally.

**Curating the pool is a separate permission from tagging a record.** Most
people should be able to tag things; far fewer should be able to rename a tag
out from under everyone.

**Free text is allowed in the input.** Forcing people to pick from a list means
the list never grows; normalisation is what keeps it tidy instead.

## Existing databases

A project adding this to an established database inherits whatever is already
there:

```sh
php artisan tags:dedupe --dry-run   # show what would merge
php artisan tags:dedupe             # do it
```

It groups by normalised slug and keeps the most-used name — the one people
already recognise.

## Frontend

- `AppTagInput` — chips + autocomplete for one record. Props: `type`,
  `recordId`, `tagType`, `label`, `readonly`. Emits `change`.
- `AppTagFilter` — multi-select over the pool, showing usage counts.
  `v-model` is an array of slugs.
- `useTags(type?)` — the pool: `tags`, `loading`, `loaded`, `fetch(search?)`.
- `useRecordTags(type, id)` — one record: `tags`, `saving`, `fetch()`,
  `sync(names)`. `sync` takes the server's list back, so chips show the
  canonical tag rather than what was typed.

## Who can browse the tag pool

Tags **on a record** are gated by the ability registered for that type — the
same check that governs reading the record. `GET /tags`, the pool the
autocomplete and the filters read, is the other half, and it is governed by
`TagPoolScope`.

It matters because tag names are rarely neutral. They carry exactly the internal
judgement a project does not publish — `at-risk`, `legal-hold`, `do-not-contact`,
`vip` — and the endpoint returns a `usage_count` beside each one. Reading the
pool does not say WHICH records carry a tag, but it does say the category exists
and roughly how heavily it is used.

The shipped `NullTagPoolScope` allows everything, matching SavedViews' `NullScope`
and for the same reason: a module that refused unknown types would make tag
autocomplete vanish on install for reasons nobody could trace, and most projects
tag nothing sensitive. Bind your own the moment one is:

```php
// AppServiceProvider::register()
$this->app->bind(TagPoolScope::class, StaffOnlyTagPool::class);

class StaffOnlyTagPool implements TagPoolScope
{
    public function allows(?string $type, mixed $user): bool
    {
        return match ($type) {
            'legal', 'risk' => $user?->can('staff.access') ?? false,
            default         => true,
        };
    }

    public function apply(Builder $query, mixed $user): void
    {
        $query->where('tenant_id', $user->tenant_id);
    }
}
```

A refused pool answers 404, not 403 — a refusal that differs from "there is
nothing here" tells the caller which pools exist.

A Tag's `type` is a free-form grouping column, **not** a `TaggableRegistry`
alias, which is why this is a seam rather than a lookup: there is no ability to
re-run and nothing for the module to infer.
