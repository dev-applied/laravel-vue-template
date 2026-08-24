# CLAUDE.md

Project-level instructions for AI agents (Claude Code primarily) working in this template or any project bootstrapped from it. Read this before touching anything else.

## Project type

Applied Imagination Laravel + Vue + Vuetify SPA template. Backend: Laravel 12 / PHP 8.4 / Sanctum / Pest 4. Frontend: Vue 3 (Options API) / Vuetify 3 / Pinia / Vite 7. Designed to be re-bootstrapped per client project, then customized.

## Things to NEVER do

- **Never run `artisan`, `composer`, or `npm` on the host.** Always `docker compose exec webserver <cmd>`. MySQL and Redis live on the Traefik network and host commands can't see them.
- **Never hardcode hex / rgba colors in SCSS or templates.** Use Vuetify theme tokens (`rgb(var(--v-theme-primary))` etc.) so brand themes work.
- **Never mock the database in integration / Feature tests.** Use a real DB. Sqlite-in-memory is fine for `tests/Unit`; `RefreshDatabase` against the project's mysql in `tests/Feature`.
- **Never run `migrate:fresh` without verifying it targets a test database.** It will wipe dev data otherwise.
- **Never use `v-if` / `v-else` on elements with pointer or drag listeners** — they tear down and re-create the DOM node, breaking event listeners mid-interaction. Use `v-show`.
- **Never silently swallow errors.** Surface to the user via `this.$error(...)` / `$messages` or report to Sentry. AppServerValidationForm handles 422 automatically; everything else should route through the errorHandler plugin.
- **Never mix `<script setup>` with Options API in this codebase.** Every page/component uses `defineComponent` + Options API. A full migration is tracked as a separate XL effort — until then, match what's there.
- **Never generate or guess URLs.** Read from `routes/api.php` or use the Wayfinder-generated TS types in `resources/ts/types/laravel/`.
- **Never put a new compose service on the `default` (`nginx-proxy`) network unless it needs Traefik or the shared MySQL/Redis.** That network is shared by every stack on the machine, and each one registers its service names as DNS aliases there — so the generic names this template uses (`webserver`, `frontend`) resolve ambiguously and Docker round-robins between unrelated client projects. Put service-to-service traffic on the project-scoped `app` network instead. See the networking section in `README.md`.

## How this template works

- **Auth**: a **module**, not in the template by default — `php artisan module:add Auth` (Sanctum, or Sanctum + Passport OAuth). It provides login/me/logout + impersonation + forgot-password, the `/mcp` endpoint (Sanctum-auth), and the optional OAuth 2.1 layer. Frontend flow is `resources/ts/plugins/auth.ts` (`this.$auth`); the kernel's `LOGIN` route name is registered by the module (see `resources/ts/router/kernel-routes.ts`). A bare template has no login until the module is added. See `docs/Authentication.md`.
- **HTTP**: axios wrapped in `resources/ts/plugins/axios.ts`, exposed as `this.$http`. Auto-injects auth header, handles 401 → logout, surfaces validation errors via the message store.
- **Routing (frontend)**: Custom DSL on top of vue-router in `resources/ts/router/`. `RouteDesigner` / `RouteBuilder` / `RouteGroup` define routes with middleware pipelines (Authentication / Authorization / Guest / ForceTypes). Use `this.$routeTo(this.ROUTES.X)` to navigate.
- **Type generation**: `composer typescript` runs `wayfinder:generate` to emit TS types into `resources/ts/types/laravel/` from Laravel routes/controllers. Re-run after backend changes.
- **WhoDidIt**: `app/Traits/WhoDidIt.php` + `WhoDidItMixin` adds `created_by` / `updated_by` to any model. Use `$table->whoDidIt()` in migrations to add the columns.
- **File pipeline**: `app/Models/File.php` + `FileController.php` handle upload, sized variants, signed download/view. Frontend wrappers: `AppFileUpload`, `AppFileDropzone`, `useFileUpload`.
- **Form validation**: `AppServerValidationForm` wraps `<v-form>`, auto-displays 422 server errors per field. Use it for every form that hits a Laravel endpoint with a FormRequest.
- **Sentry**: wired both sides. `sentry_logs` log channel in `config/logging.php`. `app/Exceptions/Sentry.php` hooks the exception handler.

## Common commands

```sh
# Tests
docker compose exec webserver composer ci                # pint --test + pest --parallel
docker compose exec webserver ./vendor/bin/pest modules/Auth/Tests/Feature/AuthTest.php

# Code style (auto-fix)
docker compose exec webserver composer format            # pint --parallel

# DB
docker compose exec webserver php artisan migrate
docker compose exec webserver php artisan migrate:fresh --seed   # ⚠️ wipes data

# Frontend type-gen
docker compose exec webserver composer typescript

# Scaffold a Vue page (writes resources/ts/pages/<Name>.vue from stub)
docker compose exec webserver php artisan vue:make-page

# Create a user
docker compose exec webserver php artisan create:user
```

## Modules

Full-stack vertical slices in `modules/<Name>/`, copy-in vendored from the
firm modules repo — **never composer-required**. Read `docs/modules.md` before
authoring or touching one. The short version: PSR-4 `Modules\`, providers
auto-registered by `ModuleLoaderServiceProvider`, frontend half in
`modules/<Name>/resources/ts/` registered via router globs, pages passed as
**lazy imports never strings**, modules declare their own middleware/layout,
`module.json` carries the version stamps the update flow depends on. Add with
`php artisan module:add` (interactive multiselect with no args; needs
`MODULES_GITHUB_TOKEN` in .env or `--from=<local checkout>`); check drift with
`php artisan module:outdated`. After adding/removing a module:
`composer dump-autoload`, `php artisan route:clear` **before** any build
(Wayfinder reads the cached route table), `composer typescript`, restart vite.
`modules/Example` is the living reference — copy its shape.

## Where to look

- Backend conventions → `app/CLAUDE.md`
- Frontend conventions → `resources/ts/CLAUDE.md`
- DB conventions → `database/CLAUDE.md`
- Component library inventory → `README.md` (top-level)
- Custom router DSL → `resources/ts/router/index.ts`
- Auth plugin → `resources/ts/plugins/auth.ts`
- HTTP plugin → `resources/ts/plugins/axios.ts`

## When in doubt

If this file disagrees with the code, trust the code. Open a PR fixing this file.

## Project Context

Context docs in `.claude/context/`. Index: `.claude/context/index.md`.

**Read before feature work:**
- `.claude/context/roadmap.md` — in-flight work + integration points (parallel-session sync)
- `.claude/context/features/<area>/<feature>.md` — feature spec + per-persona matrix
- `.claude/context/personas/index.md` — affected personas

**Persona × feature split:** Feature file = spec (matrix). Persona file = narrative (why). Read both.

**Decisions:** Use supersession. New ADR with `supersedes:` frontmatter; old ADR set `status: Superseded` + `superseded-by:`. Never edit history.

**Roadmap format (status must stay scannable at a glance):**

The roadmap groups work **by concept** — one `##` heading per initiative / area / standalone item — NOT by status. Status lives entirely in a per-leaf marker:

- `- [x]` **done** — append `— YYYY-MM-DD`
- `- [~]` **in progress** — append its `branch` / `worktree` (a parallel session greps this to avoid collisions; never omit it)
- `- [ ]` **queued** / not started
- `- [!]` **blocked** — append the reason / `blocked-on:`

**One line carries one status. Never encode status in prose, and never mix statuses in the same bullet.** A queued leaf may carry one *why-not* qualifier (`depends-on:` / `awaiting:`) but never a completion claim.

❌ Never (done + pending crammed into one prose bullet):
`P1 · Customer forms: F-15/F-16 done 2026-06-24. Remaining: F-23`

✅ Always (one status per line — scan the left gutter):
```
- [x] F-15 launcher presence cue — 2026-06-24
- [~] F-16 phone-optional-on-Video — feature/phone-optional
- [ ] F-23 retain name/email/phone on cancel
```

**Heat-ordering is load-bearing** (it replaces a status `In Progress` section): a concept with any `[~]` leaf sorts to the **top** of the file; fully-queued concepts sit below. This is how a fresh parallel session finds what's hot at a glance.

**Recently Done (last 30 days)** is the one status-named section — keep it at the bottom as a time-bounded shipping log with merge detail. A concept whose every leaf is `[x]` collapses to a one-line entry there and drops off the active list. A substantial `[x]` leaf may carry a `*(detail in Recently Done)*` pointer; small leaves just stay `[x]` in place. Never duplicate a leaf's full detail in both places. Trim entries older than 30 days.

**Roadmap status transitions (load-bearing for parallel sessions):**

The roadmap is the bridge between parallel Claude sessions — keep it accurate. **The only maintenance action is flipping a leaf's marker in place. Leaves never move between concepts**, and there are no status sections to move them into.

- **Starting work** on a leaf (brainstorming or first edit): flip it `[ ]`→`[~]` and append its `branch` / `worktree`. Re-sort its concept to the top if it isn't already there. If the work isn't in the roadmap at all, ADD its concept (a new `##` group) with the started leaf `[~]`.
- **Mid-flight**: keep the `[~]` leaf's `branch` / `worktree` current; append newly-discovered sub-leaves or integration points to the concept.
- **Stopping work** (paused): flip the leaf `[~]`→`[ ]`, or `[!]` with a reason if it's blocked. Done `[x]` leaves keep their state.
- **Shipping work** (merged / `/ship` / `/finishing-a-development-branch`): flip the leaf `[~]`→`[x]` + ship date, in place. Add a Recently Done entry **only** for a substantial ship or a now-fully-done concept (then collapse that concept into Recently Done). Never double-book the same item in two places. Trim Recently Done past 30 days.

**Detecting the work item:**
If the user mentions a ticket ID (e.g., `ZKL-279`, `LIN-123`, `#456`), use it as the roadmap entry identifier. Fetch full details via the appropriate MCP (Jira: `mcp__plugin_atlassian_atlassian__getJiraIssue`).

**Before `/ship`:**
- Change touches code referenced in `.claude/context/features/<x>` → prompt to update that doc.
- Change is part of a roadmap leaf → apply the "Shipping work" transition above (flip `[~]`→`[x]` + date in place).
- Always flip any shipped leaf in `.claude/context/roadmap.md` to `[x]` + date before the final commit, and re-sort / collapse its concept if it changes the file's heat-order.
