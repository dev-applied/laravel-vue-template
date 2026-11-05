---
type: known-issues
---

# Known Issues

Accepted tech debt. Check here before "discovering" a bug.

## Active

### Template CI does not run on pushes to master
- **symptom:** `.github/workflows/ci.yml` has `push: branches-ignore: [main, master, staging, dev]`, so a commit pushed straight to master never triggers eslint, vitest or the Capacitor build smoke. The design assumes changes arrive by PR and the PR run covers them.
- **why-deferred:** Correct for the normal workflow. It only bites an authority level that pushes direct to master, which is a per-run decision rather than the default.
- **workaround:** `gh workflow run ci.yml --repo dev-applied/laravel-vue-template --ref master` after a direct push. The modules-repo CI also clones template master and runs its `composer ci` plus `vue-tsc` on 40 legs, so PHP and types are covered either way — vitest and the Capacitor smoke are the actual gap.
- **tracking:** noted 2026-08-25 during the module run.

### The `sso` option axis is not exercised by the template's own CI
- **symptom:** The Auth module's four `sso` variants (none / oidc / saml / oidc+saml) are only ever built in the modules repo's matrix. A template-side change that breaks one is invisible until the modules repo runs.
- **why-deferred:** The template bundles exactly one variant at a time, so there is nothing for it to check. Genuinely the modules matrix's job.
- **workaround:** none needed; noted so nobody adds a redundant template job.
- **tracking:** noted 2026-08-25.

## Resolved (last 90 days)

- **The entire Vuetify typography scale was inert** — v4 renamed `text-h*` / `text-body-*` / `text-caption` to the
  Material Design 3 names, so 81 surviving v3 usages contributed nothing and every heading took a browser default.
  Invisible to every linter and every test; found only because changing a heading's TAG for screen-reader structure
  moved its size, which a tag swap should never do. Remapped, and `bin/lint` now refuses the v3 names (2026-08-25)

- **Files vertical — broken S3 upload path** — the kernel's S3 branch had never worked: `generate-presigned-url` had a controller method and no route, `mockS3Event` called a method that did not exist, the composable polled a JSON field on an endpoint that returns a redirect, and `useFile()` default-imported a module with no default export. Resolved by rebuilding it correctly in `modules/Files` (storage option: local | s3-presigned) rather than porting the broken flow upstream (2026-08-24)
- **Module frontends were never type-checked** — `npm run build` strips types with esbuild, so `vue-tsc` had only ever run without modules installed. Added to modules CI; the first run found 53 errors including three modules whose submit buttons never rendered (2026-08-24)
- **The suite ran on sqlite :memory:** against the project's own documented convention, hiding a Booking migration that fails outright on MariaDB and two `expires_at` columns carrying `ON UPDATE CURRENT_TIMESTAMP`. Both CI workflows and phpunit.xml now use MySQL (2026-08-25)
