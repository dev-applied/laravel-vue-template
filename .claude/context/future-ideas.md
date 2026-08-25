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

- **Visible labels on the auth fields, not just placeholders.** LoginPage and
  SetPasswordPage label their inputs with `placeholder` alone, so a sighted user
  who has typed can no longer see what the field was for (WCAG 3.3.2). The
  programmatic name is stable now — an `aria-label` was added alongside — so
  this is a visual decision, not a defect: moving to Vuetify's floating `label`
  changes the look of the public login screen, which is a design call rather
  than one to make while fixing screen-reader names.

  Evidence added 2026-08-25 to make that call cheap: **27 files** across the
  kernel and modules label their inputs with `label=`. The only two that do
  not are `Auth/LoginPage.vue` and `Auth/SetPasswordPage.vue`. So this is not
  a house style being changed — it is two pages being brought to it, and the
  only question left is whether the login card's minimal look is deliberate.
