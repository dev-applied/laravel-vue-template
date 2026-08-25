# Files

File uploads with generated image variants, signed view/download, and the Vue
half that drives them — upload field, dropzone, upload button, lightbox, and the
`$file` global.

## Install

```sh
php artisan module:add Files
```

**`storage`** — one option, chosen at install:

| Choice | What happens |
| --- | --- |
| `local` (default) | Multipart POST to the app, variants generated in the request. |
| `s3-presigned` | Client PUTs straight to S3 against a presigned URL; variants generated afterwards by `PUT /files/process/{file}`. Adds `PresignedUploadController` and `Routes/s3.php`. |

Needs `imagine/imagine` (variant generation) and the npm packages `heic-to`
(iPhone photos arrive as HEIC) and `mime-matcher`. `module:add` records all of
them.

## Endpoints

| Route | Purpose |
| --- | --- |
| `POST /api/v1/files` | Upload (local variant). |
| `GET /api/v1/files/meta/{file}` | JSON metadata — the only pollable one, which is why it exists separately. |
| `GET /api/v1/files/view/{file}` | Redirect to a temporary URL, or stream. |
| `GET /api/v1/files/download/{file}/{size?}` | Same, as an attachment. |
| `GET /api/v1/files/{file}/{size?}` | Greedy catch-all; registered LAST on purpose. |
| `DELETE /api/v1/files/{file}` | Deletes the row **and the stored bytes**. |
| `POST /api/v1/files/generate-presigned-url` | s3-presigned only. |
| `PUT /api/v1/files/process/{file}` | s3-presigned only; generates variants. Idempotent. |

The literal-prefixed routes are registered **before** the greedy
`/files/{file}/{size?}`. Reverse them and that pattern swallows `/files/view/5`
as `file="view", size="5"` and the route 404s on model binding. The template this
module was extracted from had exactly that bug.

## Authorization

**Every file route is authorized through `Support\FileAccess`, and the shipped
default gives you only your own files.**

The module previously had none. Each route was `auth:sanctum` plus a bare
route-model bind, and `files.id` is sequential — so any authenticated user could
walk `/files/download/1..N` and read every file in the system, then `DELETE` any
of them, which unlinks the stored bytes as well as the row.

It is a seam rather than a fixed rule because a file's audience is a property of
whatever it is attached to, and only the project knows that. An avatar is
world-readable, a signed contract is not, and both are rows in this one table.

```php
// AppServiceProvider::register()
$this->app->singleton(FileAccess::class, InvoiceFileAccess::class);
```

```php
class InvoiceFileAccess implements FileAccess
{
    public function canView(File $file, ?Authenticatable $user): bool
    {
        return $user?->can('view', $file->invoice) ?? false;
    }

    public function canDelete(File $file, ?Authenticatable $user): bool
    {
        return $user?->can('update', $file->invoice) ?? false;
    }
}
```

Details worth knowing:

- **The default is `OwnerFileAccess`** — the uploader recorded by `WhoDidIt`
  (`created_by_id`, per `config/who-did-it.php`) and nobody else. It keeps the
  case that always works with no configuration and denies the rest rather than
  guessing. The first denial in a request logs a line naming this section.
- **A file with no recorded uploader is denied to everyone**, not shared with
  everyone. That covers rows written by a queue job or a seeder, where there was
  no `Auth::id()` to record.
- **A refusal is `404`, never `403`.** Ids are sequential, so a 403 answers
  "this id exists" for every id a caller cares to try — the enumeration IS the
  attack. A refusal and a missing row are indistinguishable.
- **Delete asks separately from view** (`canDelete`), because deleting removes
  the bytes.
- **`process` is gated too.** It was the same bare bind, and it runs real image
  processing on demand.

Tests that create files with a factory must set the owner —
`File::factory()->create(['created_by_id' => $user->id])` — because a row built
outside a request records no uploader.

## Frontend

| Piece | Use |
| --- | --- |
| `AppFileUpload` | A form field. Emits the created file. |
| `AppFileDropzone` | Drag-and-drop, multiple files, progress. |
| `AppFileUploadBtn` | The button-only variant. |
| `AppLightBoxImage` | Click-to-enlarge. |
| `useFileUpload` | The composable underneath all of them — `onProgress`, `folderId`. |
| `useFile` / `$file` | Resolve a file's URLs by size. |

Two `.d.ts` files, and the split is deliberate: `types.d.ts` is a MODULE file (it
has a top-level import) because augmenting `ComponentCustomProperties` requires
one, and `shims.d.ts` is a SCRIPT file (no top-level import/export) because
`declare module "mime-matcher"` for an untyped package requires that. Swap them
and one silently does nothing while the other SHADOWS Vue's own types — the
second mistake produced 823 errors.

## Not included

- **Virus scanning.** Uploads are stored as received.
- **Quotas.** Nothing caps per-user or per-project storage.
- **A folder UI.** `folderId` is threaded through the upload path for projects
  that group files, but there is no browser for it.

## Where presigned uploads may write (`s3-presigned` only)

`POST /files/generate-presigned-url` accepts an optional `path`, which becomes
the S3 key prefix of the signed PUT. It is an allow-list, not a free string: the
value decides where the caller's signed URL can write, and an S3 key is a
literal string — nothing resolves a `..` away.

The default permits one root, `uploads`. To add more, publish `config/files.php`:

```php
return [
    'upload_prefixes' => ['uploads', 'documents', 'avatars'],
];
```

Anything not on the list is a 422 on `path`. The module's own frontend never
sends this field, so leaving the default alone is the normal case.
