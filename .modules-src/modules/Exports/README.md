# Exports

Queued exports of any listing. The module ships no exportable data of its own —
a project declares what can be exported, and the module handles queueing,
streaming, storage, download and cleanup.

## Register your sources

From your `AppServiceProvider::boot()`:

```php
use Modules\Exports\Support\ExportRegistry;

app(ExportRegistry::class)->register(
    key:     'items',
    label:   'Items',
    columns: ['id' => 'ID', 'name' => 'Name', 'owner.name' => 'Owner'],
    query:   fn (array $filters) => Item::query()->filter($filters)->with('owner'),
    ability: 'viewAny',                       // optional policy check
    format:  fn ($value, $column) => $column === 'created_at'
        ? $value?->toDateString()
        : $value,                             // optional per-cell formatter
);
```

- **`columns`** is `attribute path => header label`. Dot paths work
  (`owner.name`), so eager-load the relation in `query` or you will N+1 per row.
- **`query`** is resolved fresh inside the job and receives the filters the user
  had applied. Return a builder, never a collection — the job streams it with
  `chunkById`.
- **`ability`** is checked before the job is queued, so a user cannot export a
  listing they are not allowed to read.
- The registry is an explicit allow-list: an unregistered key fails validation.

## Use it in the UI

```vue
<AppExportButton source="items" :filters="filters" :formats="['csv', 'xlsx']" />
```

Pass the same filter object the listing uses. `ExportsPage` (`/exports`) is the
user's history, with re-download and delete.

## Formats

`csv` (default) needs no dependencies and streams with constant memory.
Installing with `--option formats=csv+xlsx` adds `openspout/openspout` and the
XLSX writer; the `csv` choice drops both.

## Queue

Generation is a queued job, so a worker must be running. With
`QUEUE_CONNECTION=sync` it runs inline and the request simply takes longer.
