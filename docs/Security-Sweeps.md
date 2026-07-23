# Security Sweeps

Log of periodic security-debt sweeps on the template repo. Append a section per sweep so the next person can see the cadence and pick up where the last one left off.

## Sweep — 2026-07-23

**Trigger:** 37 open Dependabot alerts on `master` (1 critical, 7 high, 23 moderate, 6 low) — 23 composer + 14 npm. Normal accumulation since the 2026-05-14 sweep; the `dependabot.yml` `target-branch` root cause was already fixed then, so auto-PRs *are* opening now (15 open Dependabot PRs at sweep time), they just hadn't been merged. CI is disabled on this repo, so nothing lands automatically.

**Approach:** targeted `composer update` of the vulnerable packages (`-W`) + `npm update`, both run inside the worktree's isolated docker stack. No inline replacements needed this round — every fix was an in-range or minor version bump.

### Before / after

| Metric | Before | After |
| --- | --- | --- |
| Dependabot alerts (GH) | 37 (1 crit, 7 high, 23 mod, 6 low) | 0 expected after merge |
| `composer audit` | 26 advisories / 11 packages | 0 |
| `npm audit` | 11 (2 crit, 7 high, 2 low) | 0 |

### Composer bumps (`composer update … -W`)

| Package | From | To | Notes |
| --- | --- | --- | --- |
| laravel/framework | 12.58.0 | 12.64.0 | 1 high + 1 moderate advisory |
| guzzlehttp/guzzle | 7.10.0 | 7.15.1 | 7 advisories |
| guzzlehttp/psr7 | 2.9.0 | 2.13.0 | 5 advisories (composer audit flagged `<2.12.3`, newer than the Dependabot alert) |
| symfony/http-kernel, http-foundation, mime, mailer, routing, yaml, polyfill-intl-idn | 7.4.8 / 1.37.x | 7.4.14 / 1.38.x | pulled transitively via `-W` |
| mtdowling/jmespath.php | 2.8.0 | 2.9.2 | CVE-2026-54133 (code injection) — **not yet surfaced by Dependabot**; caught by `composer audit` |

### npm bumps

Direct dep floors raised in `package.json` to lock the patched minimums (`npm update` already resolved them; the floor raise prevents regression):

| Package | From | To |
| --- | --- | --- |
| axios | `^1.15.2` | `^1.18.1` |
| vite | `^7.3.2` | `^7.3.6` |
| vitest | `^3.0.0` | `^3.2.7` |

Transitive fixes via `npm update` (within existing ranges): `brace-expansion` → 5.0.8, `form-data` → 4.0.6, `js-yaml` → 4.3.0, `tar` → 7.5.21, `@babel/core` → 7.29.7, `esbuild` → 0.28.1, `ws` → 8.21.1.

### Verification

```sh
composer audit            # No security vulnerability advisories found
npm audit                 # found 0 vulnerabilities
npm run build             # vite build clean (25.9s)
npm run lint              # 0 warnings
npm run test:ci           # vitest 8/8 pass
```

Backend tests: `pest` shows **3 pre-existing failures in `tests/Feature/AuthTest.php`**, confirmed identical on `master`'s pre-bump `composer.lock` (Laravel 12.58.0) — so **this sweep introduces no regression**. Root cause is two template test-infra bugs, not dependencies (see below).

### Out of scope, surfaced but deferred

- **Template test-infra bug #1 — cache env var.** `config/cache.php` reads `env('CACHE_DRIVER', 'file')` (Laravel ≤10 name), but `phpunit.xml` pins the Laravel 11+ name `CACHE_STORE=array`. The override is silently ignored, so tests run against real **Redis** instead of the intended array store. Fix: rename to `CACHE_STORE` in `config/cache.php` + `.env.example` (+ any `.env`), or add `<env name="CACHE_DRIVER" value="array"/>` to `phpunit.xml` as a surgical stopgap.
- **Template test-infra bug #2 — throttle not reset between tests.** `AuthTest.php` `beforeEach` clears `RateLimiter::clear('login:127.0.0.1')`, but the login route uses an anonymous `throttle:6,1` (routes/api.php:15) keyed by a request hash, not that name. The throttle counter accumulates across tests → later logins get 429. Fix: `Cache::flush()` (or clear the real limiter) in `beforeEach`, and reconcile the `login is rate limited` assertion (it expects 422 on the 7th attempt but `throttle:6,1` returns 429).
- `vue-tsc --noEmit` still surfaces the pre-existing type errors from prior sweeps (unrelated).
- Vite still emits the 500 kB `main-*.js` chunk-size warning — long-standing, not a security issue.

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
