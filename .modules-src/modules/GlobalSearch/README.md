# GlobalSearch

Cross-model search behind one endpoint, with a Cmd/Ctrl+K command palette in
front of it.

A project declares what is searchable; the module owns the endpoint, the
per-source authorisation, the grouping and the UI. It needs **no search
engine** — a source is a closure returning a builder, so the default install is
plain SQL against tables you already have.

## Registering a source

From `AppServiceProvider::boot()`:

```php
app(\Modules\GlobalSearch\Support\SearchRegistry::class)->register(
    key:      'items',
    label:    'Items',
    query:    fn (string $term) => Item::query()
        ->where('name', 'like', "%{$term}%")
        ->with('owner'),
    title:    fn (Item $item) => $item->name,
    subtitle: fn (Item $item) => $item->owner?->name,
    route:    fn (Item $item) => ['name' => 'items.edit', 'params' => ['id' => $item->id]],
    icon:     'inventory_2',
    ability:  'viewAny',        // optional Gate ability
    order:    0,                // lower sorts first; ties break on label
);
```

`query` is a closure and not a model-plus-columns pair because the interesting
sources are never a flat LIKE. A project searching orders wants the customer's
name too, and that join is not something this module can guess. Handing over
the builder means the project owns its own definition of "matches" — including
full-text, a trigram index, or Scout.

### Why a registry and not a `Searchable` trait

The search surface is an explicit allow-list. With a trait, every model that
ever adopts it becomes searchable by everyone who can reach the endpoint, and
the mistake is silent: add a column to an existing searchable model and it
quietly becomes readable through search. Here, nothing is searchable until
somebody writes it down — the same reasoning as `Exports`.

## Authorisation

`ability` is checked per source, per request, through `Gate::forUser()`.

An unauthorised source is **omitted, not returned empty**. Returning it empty
would tell the caller the type exists and that they matched nothing in it —
a different statement, and one they are not entitled to. `types[]` validates
against what is *registered* rather than what the caller may reach, for the
same reason: rejecting an unauthorised type by name would answer the question.

There is no ownership filtering inside a source. `query` is yours; if rows are
per-user or per-tenant, scope them there.

## Endpoints

| Method | Path | Notes |
|---|---|---|
| `GET` | `/api/v1/search?q=&types[]=&limit=` | `q` min 2 chars, `limit` 1–25 (default 5) **per source** |
| `GET` | `/api/v1/search/types` | The sources this user may search — the palette's filter chips |
| `GET` | `/api/v1/search/history` | `history=per-user` only |
| `POST` | `/api/v1/search/history` | `history=per-user` only |
| `DELETE` | `/api/v1/search/history[/{id}]` | `history=per-user` only |

All require `auth:sanctum`.

Results are **grouped by type, not interleaved**. Ranking across sources is not
something this module can do honestly — "3 of 40 items" and "1 of 1 user" carry
no comparable score, and inventing one would pin whichever source was
registered first to the top forever.

`hasMore` is computed by fetching `limit + 1` rows and slicing one off, so a
palette that queries on every keystroke does not also run a `COUNT` per source
per keystroke.

## Frontend

Mount the palette **once**, in your layout:

```vue
<script lang="ts">
import AppGlobalSearch from "@modules/GlobalSearch/resources/ts/components/AppGlobalSearch.vue"
import AppGlobalSearchButton from "@modules/GlobalSearch/resources/ts/components/AppGlobalSearchButton.vue"
</script>

<template>
  <v-app-bar>
    <AppGlobalSearchButton />
  </v-app-bar>
  <AppGlobalSearch />
</template>
```

Open it from anywhere:

```ts
import {useGlobalSearch} from "@modules/GlobalSearch/resources/ts/composables/useGlobalSearch"

useGlobalSearch().openSearch("optional starting query")
```

The open state lives at module scope, not per component: the palette is mounted
once and every toolbar button, empty-state link and keyboard shortcut has to
reach that same instance.

Cmd/Ctrl+K is bound while the palette is mounted. The button exists because a
shortcut nobody knows about is not a feature — it shows the platform-correct
hint.

Behaviour worth knowing:

- **Requests are sequenced.** Typing `inv` then `invoice` can otherwise paint
  the `inv` results last, leaving the list disagreeing with the box.
- **250ms debounce** — one request per typing burst.
- Arrow keys move a cursor across the flattened result list, Enter opens,
  Esc closes. The list is a `role="listbox"` with `aria-selected` tracking the
  cursor, and the no-results line is an `aria-live` region.
- History is recorded on **choose**, never on keystroke.
- If the `history=none` variant is installed the history endpoints 404, and the
  palette hides the recents section rather than reporting an error.

## Options

| Option | Default | Effect |
|---|---|---|
| `history` | `per-user` | `none` drops the table, model, controller, routes and test together |
| `scout` | `off` | `on` adds `laravel/scout` and `ScoutSearchSource`, a helper that builds a source closure from a Scout model |

`scout=on` is only worth it when the project already runs Scout against a real
engine. On Scout's own `database` driver the helper is a slower LIKE than
writing the LIKE yourself, because it round-trips through Scout's builder first.

## Tests

`Tests/Feature/GlobalSearchTest.php` covers grouping, per-source presentation,
authorisation invisibility, `types[]` narrowing, the 2-character floor,
`hasMore` without a second query, and registry ordering.
`SearchHistoryTest.php` covers the collapse-on-repeat rule and the ownership
boundary on read, delete and clear.
