---
type: future-ideas
---

# Future Ideas

Uncommitted ideas. Graduate to `roadmap.md` when committed to build.

## Ideas

- **A folder browser for Files.** `folder_id` is now recorded, exposed and
  indexed on both upload paths, but the module ships no UI for navigating
  folders and no folders table — deliberately, since it does not own that
  concept. A project-side example (a folders table plus a tree component) would
  make the column useful without the module claiming ownership of it.

- **Bucket-level scanning as the first line for `s3-presigned`.** `FileScanner`
  runs in `process()` on that path, which is after the object has landed — the
  honest limit of a presigned design. A companion recipe (S3 event → scanner →
  delete-on-infected) would let the seam be the second line rather than the only
  one.

- **Content-length-range on the presigned PUT.** The storage quota is enforced
  before the write on `local` and after it on `s3-presigned`, because the app
  never learns the size until the object exists. A presigned POST policy with a
  content-length-range condition would let S3 refuse an oversized upload itself.

- **An `Example` module README.** It is the only other module without one. Its
  twelve files arguably ARE the documentation — `module.json` calls it the
  reference module — so this may only ever be three lines pointing at that
  intent.

- **A `Tasks` board scope example.** `TaskScope` ships permissive with a null
  implementation. A worked `TeamScope` in the README, the way SavedViews shows
  a tenant scope, would make the seam obviously usable rather than obviously
  present.
