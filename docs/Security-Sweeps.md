# Security Sweeps

Log of periodic security-debt sweeps on the template repo. Append a section per sweep so the next person can see the cadence and pick up where the last one left off.

## Sweep — 2026-05-14

**Trigger:** 44 open Dependabot alerts on `master` (21 high, 21 moderate, 2 low) accumulated across multiple sessions without auto-PRs.

**Root cause of the backlog:** `.github/dependabot.yml` targeted `main` for all three ecosystems (composer / npm / github-actions). The repo's default branch is `master` — so weekly Dependabot auto-PR generation has been silently failing. Alerts were still scanned and surfaced, but the proposed-fix PRs were never opening.

### Before / after

| Metric | Before | After |
| --- | --- | --- |
| Dependabot alerts (GH) | 44 (21 high, 21 mod, 2 low) | 0 expected after merge |
| `npm audit` total | 44 | 0 |
| `npm audit` high | 14 | 0 |
| `composer audit` | 0 (already clean) | 0 |

### Direct bumps (package.json)

| Package | From | To (resolved) | CVEs cleared |
| --- | --- | --- | --- |
| axios | `^1.12.0` | `^1.15.2` → 1.16.1 | 12 |
| vite | `^7.2.7` | `^7.3.2` → 7.3.3 | 2 |
| @unhead/vue, @unhead/addons | `^2.0.0` | `^2.1.13` → 2.1.15 | 3 |

### Transitive bumps (via `npm update --package-lock-only`)

minimatch, picomatch, brace-expansion, flatted, rollup, postcss, ajv, follow-redirects, immutable — all raised to patched versions within their existing semver ranges. Cleared the remaining transitive CVEs (~15 alerts).

### Inline replacements (no upstream patch)

| Package | CVE | Replacement |
| --- | --- | --- |
| lodash.pick | CVE-2020-8203 (Prototype Pollution) | 6-line typed `pick()` helper in `resources/ts/components/AppTable.vue` |
| lodash.trim | CVE-2020-28500 (ReDoS) | `s.replace(/^\/+|\/+$/g, '')` in `router/Route.ts` and `router/RouteBuilder.ts` (only ever called with `/` arg) |

### Dropped declared-but-unused

- `lodash.debounce` (+ `@types/lodash.debounce`)
- `lodash.isfunction` (+ `@types/lodash.isfunction`)

### Config fix

`.github/dependabot.yml` — flipped `target-branch: main` → `master` on composer, npm, and github-actions sections. Future Dependabot auto-PRs will actually open this time.

### Out of scope, surfaced but deferred

- `vue-tsc --noEmit` surfaces ~20 pre-existing type errors unrelated to this sweep (vue-router 4 type changes around `RawLocation`/`$route.params`, leftover spatie/permission references on `AuthUser`, Vue 2-style mixin types). Fix as their own PR / loop.
- Vite build emits a 500kB-chunk-size warning for `main-*.js`. Has been there since long before this sweep; not a security issue.

### Verification commands

```sh
npm audit                          # 0 vulnerabilities
composer audit                     # 0 advisories (run inside docker compose exec)
npm run build                      # vite build clean
npm run lint                       # 0 warnings (used to flag 2)
```

### Cadence

Run a sweep whenever the open-alerts count goes over ~10 or once a quarter, whichever comes first. The new dependabot config should keep new alerts at <5 if grouped PRs land weekly.
