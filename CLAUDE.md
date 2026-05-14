# CLAUDE.md

Project-level instructions for AI agents (Claude Code primarily) working in this template or any project bootstrapped from it. Read this before touching anything else.

## Project type

Applied Imagination Laravel + Vue + Vuetify SPA template. Backend: Laravel 12 / PHP 8.4 / Sanctum / Pest 4. Frontend: Vue 3 (Options API) / Vuetify 3 / Pinia / Vite 7. Designed to be re-bootstrapped per client project, then customized.

## Things to NEVER do

- **Never run `artisan`, `composer`, or `npm` on the host.** Always `docker compose exec $DOCKER_ROUTER <cmd>`. MySQL and Redis live on the Traefik network and host commands can't see them.
- **Never hardcode hex / rgba colors in SCSS or templates.** Use Vuetify theme tokens (`rgb(var(--v-theme-primary))` etc.) so brand themes work.
- **Never mock the database in integration / Feature tests.** Use a real DB. Sqlite-in-memory is fine for `tests/Unit`; `RefreshDatabase` against the project's mysql in `tests/Feature`.
- **Never run `migrate:fresh` without verifying it targets a test database.** It will wipe dev data otherwise.
- **Never use `v-if` / `v-else` on elements with pointer or drag listeners** — they tear down and re-create the DOM node, breaking event listeners mid-interaction. Use `v-show`.
- **Never silently swallow errors.** Surface to the user via `this.$error(...)` / `$messages` or report to Sentry. AppServerValidationForm handles 422 automatically; everything else should route through the errorHandler plugin.
- **Never mix `<script setup>` with Options API in this codebase.** Every page/component uses `defineComponent` + Options API. A full migration is tracked as a separate XL effort — until then, match what's there.
- **Never generate or guess URLs.** Read from `routes/api.php` or use the Wayfinder-generated TS types in `resources/ts/types/laravel/`.

## How this template works

- **Auth**: Sanctum. SPA cookie auth for browser; bearer (personal access token) for mobile/Capacitor. Routes live in `routes/api.php`. Frontend auth flow is in `resources/ts/plugins/auth.ts` exposed as `this.$auth`.
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
docker compose exec $DOCKER_ROUTER composer ci                # pint --test + pest --parallel
docker compose exec $DOCKER_ROUTER ./vendor/bin/pest tests/Feature/AuthTest.php

# Code style (auto-fix)
docker compose exec $DOCKER_ROUTER composer format            # pint --parallel

# DB
docker compose exec $DOCKER_ROUTER php artisan migrate
docker compose exec $DOCKER_ROUTER php artisan migrate:fresh --seed   # ⚠️ wipes data

# Frontend type-gen
docker compose exec $DOCKER_ROUTER composer typescript

# Scaffold a Vue page (writes resources/ts/pages/<Name>.vue from stub)
docker compose exec $DOCKER_ROUTER php artisan vue:make-page

# Create a user
docker compose exec $DOCKER_ROUTER php artisan user:create
```

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
