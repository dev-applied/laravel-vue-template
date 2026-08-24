---
type: known-issues
---

# Known Issues

Accepted tech debt. Check here before "discovering" a bug.

## Active

### [Title]
- **symptom:** [What goes wrong]
- **why-deferred:** [Reason]
- **workaround:** [If any]
- **tracking:** [Ticket/decision link]

## Resolved (last 90 days)

- **[Title]** — [summary] (YYYY-MM-DD)

## Files vertical — broken S3 upload path (found 2026-08-24 during module extraction)

The template advertises "the File pipeline" as a kernel guarantee in
`docs/modules.md`, but the S3 branch of `useFileUpload` has never worked:

- `POST /files/generate-presigned-url` — controller method exists, **route does not**. 404.
- `PUT /files/mock-s3-event/{id}` — **route does not exist**, and
  `FileController::mockS3Event()` calls `$file->mockS3Event()`, which is not
  defined on `App\Models\File`. Fatal if it were ever reached.
- The composable then polls `GET /files/view/{id}` for `response.data.file.processed`.
  `view()` returns a redirect or a BinaryFileResponse, never JSON, and there is
  no `processed` column on the `files` table.
- `useFile.ts` does `import file from '@/plugins/file'`, but `plugins/file.ts`
  exports only named functions — no default. `useFile()` returns `{file: undefined}`.

Anything with `VITE_FILESYSTEM_DISK=s3` therefore fails on first upload.

Resolved by rebuilding it correctly in `modules/Files` (storage option:
local | s3-presigned) rather than porting the broken flow upstream.
