# DataImport

Spreadsheet imports with a column-mapping wizard. The counterpart to
`modules/Exports`, and deliberately symmetric with it: a project registers
named targets, and the module owns upload, mapping, validation and reporting.

## Register a target

From `AppServiceProvider::boot()`:

```php
use Modules\DataImport\Support\ImportRegistry;

app(ImportRegistry::class)->register(
    key:      'items',
    label:    'Items',
    fields:   ['name' => 'Name', 'status' => 'Status'],
    rules:    ['name' => 'required|string|max:255', 'status' => 'required|in:draft,live'],
    required: ['name'],
    handler:  fn (array $row) => Item::updateOrCreate(['name' => $row['name']], $row),
    ability:  'create',   // optional
);
```

- **`fields`** is the shape a row takes *after* mapping; the wizard renders one
  selector per field.
- **`rules`** validates each mapped row.
- **`handler`** persists one row. Persistence stays in a closure the project
  owns, so the module never guesses how a client's model should be created or
  matched for updates.
- The registry is an allow-list: an import writes to the database, so the set of
  writable targets must be something a developer deliberately exposed.

## The flow

`/imports/new` walks upload → map → preview → import.

The **dry run is the point**. It reports exactly which lines would fail, and
why, before anything is written. An importer that only tells you afterwards is
one people work around.

## Behaviour worth knowing

- **A bad row fails alone.** Each row is validated and persisted in its own
  transaction, so a 5,000-row file with one bad row imports 4,999 and names the
  one. Wrapping the batch would throw all of it away.
- **A throwing handler fails only its row**, and its message lands in the error
  report.
- **Errors are capped** at 100 retained entries; `failedRows` still counts every
  one, and the UI says the list was trimmed.
- **Empty cells become `null`**, not `''` — otherwise `nullable|integer` fails
  on a blank column in a way nobody can diagnose.
- **A UTF-8 BOM is stripped** from the first header. Excel writes one, and
  without this the first column never matches a mapping.
- Files stream row by row; nothing loads the whole spreadsheet.
- Imports are scoped to the uploading user, and deleting one deletes its file.

## Queue

The commit step is queued, so a worker must be running. With
`QUEUE_CONNECTION=sync` it runs inline and the request just takes longer.
