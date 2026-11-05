# CLAUDE.md

Project-level instructions for AI agents (Claude Code primarily) working in this template or any project bootstrapped from it. Read this before touching anything else.

## Project type

Applied Imagination Laravel + Vue + Vuetify SPA template. Backend: Laravel 13 / PHP 8.4 / Sanctum / Pest 4. Frontend: Vue 3 / Vuetify 4 / Pinia / Vite 7. Designed to be re-bootstrapped per client project, then customized.

Work on the template itself is tracked in `docs/template-maintenance/` (roadmap, known issues, ideas). `.gitattributes` keeps that folder out of bootstrapped projects, so in a client project it does not exist.

## Things to NEVER do

- **Never run `artisan`, `composer`, or `npm` on the host.** Always `docker compose exec webserver <cmd>`. MySQL and Redis live on the Traefik network and host commands can't see them.
- **Never hardcode hex / rgba colors in SCSS or templates.** Use Vuetify theme tokens (`rgb(var(--v-theme-primary))` etc.) so brand themes work.
- **Never mock the database in tests.** Use a real database — `RefreshDatabase`, real migrations, real queries. Note what the suite actually runs on today: `phpunit.xml` sets `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` for **every** suite including `tests/Feature`, not the project's mysql. That is fast and is what module CI uses, but it will not catch MySQL-specific behaviour (JSON column semantics, strict-mode errors, collation). Write portable queries, and reach for the mysql container when a change depends on engine behaviour.
- **Never run `migrate:fresh` without verifying it targets a test database.** It will wipe dev data otherwise.
- **Never use `v-if` / `v-else` on elements with pointer or drag listeners** — they tear down and re-create the DOM node, breaking event listeners mid-interaction. Use `v-show`.
- **Never silently swallow errors.** Surface to the user via `this.$error(...)` / `$messages` or report to Sentry. AppServerValidationForm handles 422 automatically; everything else should route through the errorHandler plugin.
- **Pages and layouts are Options API; leaf components are `<script setup>`.** A page or layout leans on the `this.$*` globals (`$http`, `$auth`, `$routeTo`, `$confirm`) that `<script setup>` cannot reach, so it gets `defineComponent`; a presentational component takes props and emits events, so it gets `<script setup>`. Both styles are in `components/` and both are correct — pick by what the component needs, not by which is more common, and never mix the two inside one file. (A component needing only `$vuetify` can go either way: `useDisplay()` in setup, `this.$vuetify` in Options.)
- **Never generate or guess URLs.** Read from `routes/api.php` or use the Wayfinder-generated TS types in `resources/ts/types/laravel/`.
- **Never put a new compose service on the `default` (`nginx-proxy`) network unless it needs Traefik or the shared MySQL/Redis.** That network is shared by every stack on the machine, and each one registers its service names as DNS aliases there — so the generic names this template uses (`webserver`, `frontend`) resolve ambiguously and Docker round-robins between unrelated client projects. Put service-to-service traffic on the project-scoped `app` network instead. See the networking section in `README.md`.

## How this template works

- **Auth**: a **module**, not in the template by default — `php artisan module:add Auth` (Sanctum, or Sanctum + Passport OAuth). It provides login/me/logout + impersonation + forgot-password, the `/mcp` endpoint (Sanctum-auth), and the optional OAuth 2.1 layer. Frontend flow is `resources/ts/plugins/auth.ts` (`this.$auth`); the kernel's `LOGIN` route name is registered by the module (see `resources/ts/router/kernel-routes.ts`). A bare template has no login until the module is added. See `docs/Authentication.md`.
- **HTTP**: axios wrapped in `resources/ts/plugins/axios.ts`, exposed as `this.$http`. Auto-injects auth header, handles 401 → logout, surfaces validation errors via the message store.
- **Routing (frontend)**: Custom DSL on top of vue-router in `resources/ts/router/`. `RouteDesigner` / `RouteBuilder` / `RouteGroup` define routes with middleware pipelines (Authentication / Authorization / Guest / ForceTypes). Use `this.$routeTo(this.ROUTES.X)` to navigate.
- **Type generation**: `composer typescript` runs `wayfinder:generate` to emit TS types into `resources/ts/types/laravel/` from Laravel routes/controllers. Re-run after backend changes.
- **WhoDidIt**: `app/Traits/WhoDidIt.php` + `WhoDidItMixin` adds `created_by` / `updated_by` to any model. Use `$table->whoDidIt()` in migrations to add the columns.
- **File pipeline**: not in the kernel — ships as `modules/Files` (`php artisan module:add Files`). Upload, sized variants, signed download/view, plus its own Vue half (`AppFileUpload`, `AppFileDropzone`, `AppFileUploadBtn`, `AppLightBoxImage`, `useFileUpload`, `$file`). Its `storage` option picks direct upload or presigned S3.
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

**Editing a module inside the template: copy the change into `.modules-src/`
too.** That directory is the tracked source `module:add --from=.modules-src`
installs from, so a fix applied only to `modules/` runs here and is absent from
the next project bootstrapped from the template. `module:check` compares the two
and the test suite runs it, so the gate goes red until they agree — if a batch of
unrelated `ModuleCheckCommandTest` failures appears after you edit a module, that
is what it is telling you. `module.json` is exempt; `module:add` writes
`installed_options` into the installed copy by design.

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
<!-- context-init:block 3 -->

Business and persona docs live in `.claude/context/` (start at `index.md`). Code shows what; these show why, for whom, and what is in flight.

- **Before feature work:** read `roadmap.md`, the feature's doc under `features/` (the spec), and the personas it affects (the why).
- **Starting work:** flip its roadmap leaf `[ ]` to `[~]` and add the branch or worktree; add the leaf if it is missing. A ticket ID is the leaf's identifier.
- **Shipping:** flip the leaf to `[x]` with `: YYYY-MM-DD`, and update any feature doc whose cited code you changed.
- **Roadmap format:** the rules are in the comment at the top of `roadmap.md`. Run `python3 .claude/context/check-roadmap.py` after editing it and before committing.
- **Decisions:** never edit an old ADR. Write a new one with `supersedes:` and set the old one to `Superseded`.
