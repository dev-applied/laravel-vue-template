<p align="center"><a href="https://appliedimagination.com" target="_blank"><img src="https://laravelvuespa.com/preview-dark.png" width="400" alt="Applied Imagination"></a></p>

# Applied Imagination — Laravel + Vue Template

Starting point for Applied Imagination client projects on the Laravel + Vue stack. Bootstrap one of these per new project and rename the things called out in the **Bootstrap a new project** checklist below.

- [Bootstrap a new project](#bootstrap-a-new-project)
- [Stack](#stack)
- [Running locally](#running-locally)
- [Common commands](#common-commands)
- [Architecture notes](#architecture-notes)
- [Deployment](#deployment)
- [For AI agents](#for-ai-agents)

## Bootstrap a new project

After cloning this template into a new project directory:

1. Set `APP_NAME`, `APP_DOMAIN`, `DOCKER_DOMAIN`, `DOCKER_ROUTER`, and `DB_DATABASE` in `.env` (copy from `.env.example` first).
2. `docker compose up -d` — boots PHP/Apache + Vite dev server on Traefik.
3. `docker compose exec <DOCKER_ROUTER> php artisan key:generate`
4. `docker compose exec <DOCKER_ROUTER> php artisan migrate`
5. `docker compose exec <DOCKER_ROUTER> php artisan db:seed`
6. Update `.github/CODEOWNERS` and `.github/workflows/*` for the new project.
7. If the project needs roles/permissions: `composer require spatie/laravel-permission && php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"` (the template ships without it — Dec 2025 L12 upgrade removed it deliberately).

## Stack

**Backend** (`composer.json`):

| Package                            | Purpose                                                       |
| ---------------------------------- | ------------------------------------------------------------- |
| `laravel/framework ^12`            | App framework (PHP ^8.4)                                      |
| `laravel/sanctum ^4.3`             | API token + SPA cookie auth                                   |
| `sentry/sentry-laravel ^4.20`      | Error & log monitoring                                        |
| `imagine/imagine ^1.3`             | Image manipulation (used by file pipeline)                    |
| `league/flysystem-aws-s3-v3 ^3.29` | S3 disk for uploaded files                                    |
| `laravel/tinker ^2.9`              | REPL                                                          |
| **Dev-only**                       |                                                               |
| `pestphp/pest ^4`                  | Test runner (parallel; type-coverage plugin installed)        |
| `laravel/pint ^1.24`               | Code style — config in `pint.json`                            |
| `laravel/wayfinder ^0.1.13`        | Generates TS types from Laravel routes/controllers            |
| `laravel/boost ^1.0`               | AI agent enablement (Laravel introspection MCP tools)         |
| `scrumble-nl/laravel-model-ts-type ^10.5` | Generates TS types from Eloquent models                |
| `spatie/laravel-ray ^1.40`         | Dev-time debugger                                             |

**Frontend** (`package.json`):

| Package                  | Purpose                                              |
| ------------------------ | ---------------------------------------------------- |
| `vue ^3`                 | Framework (Options API + `defineComponent` style)    |
| `vuetify ^3`             | Component library (Material design)                  |
| `pinia ^3`               | State (`stores/`: app, message, user)                |
| `vue-router ^4`          | Routing — uses a custom DSL in `resources/ts/router/`|
| `@sentry/vue ^10`        | Frontend error monitoring                            |
| `@unhead/vue ^2`         | Head/meta management                                 |
| `@vueuse/core ^13`       | Composition utilities                                |
| `axios ^1`               | HTTP — wrapped in the `axios.ts` plugin              |
| `dayjs`                  | Dates — wrapper in `utils/dayjs.ts`                  |
| `vite ^7`, `vitest ^3`   | Build + tests                                        |

**Component library** (`resources/ts/components/`):
`AppDialog`, `AppListTable`, `AppPaginationTable`, `AppTable`, `AppLoader`, `AppMessages`, `AppPasswordValidation`, `AppServerValidationForm`, `AppLightBoxImage`, `UpdateDetector`, plus form fields: `AppAddressField`, `AppAutoComplete` (own folder), `AppCombobox`, `AppDateInput`, `AppFileDropzone`, `AppFileUpload`, `AppFileUploadBtn`, `AppMaskField`.

**Composables** (`resources/ts/composables/`): `useAuth`, `useFile`, `useFileUpload`, `useHttp`, `usePaginationData`, `useProxy`, `useRoute`, `useTime`, `useValidators`.

**Plugins** (`resources/ts/plugins/`): `auth`, `axios`, `backButton`, `breadcrumbs`, `confirm`, `errorHandler`, `file`, `routeTo`, `versioning`, `vuetify`. Plugins register `this.$auth`, `this.$http`, `this.$error`, `this.$routeTo`, etc. as global properties — pages use the Options API style throughout.

## Running locally

### Docker (recommended)

1. Install [Traefik Dockerized](https://github.com/Devin345458/traefik-dockerized) if you haven't already.
2. Copy `.env.example` → `.env` and set `DOCKER_DOMAIN`, `DOCKER_ROUTER`.
3. `docker compose up -d`
4. Open `https://<DOCKER_DOMAIN>` (Traefik provides TLS automatically).

The `webserver` service runs Apache + PHP via `thecodingmachine/php` (Xdebug pre-wired). The `frontend` service runs Vite dev with HMR proxied through Traefik at `/hmr`.

Optional services in `docker-compose.yml` ship commented out — uncomment the block under the matching header to enable it:

| Header comment | Enables |
|----------------|---------|
| `Uncomment the 3 lines below to enable the scheduler cron` | `php artisan schedule:run` every minute |
| `Uncomment the service below to enable horizon` | Horizon queue worker |
| `Uncomment the service below to enable the stripe webhook listener` | `stripe listen` forwarding webhooks to `webserver:80` |

### Networking

Two networks are declared:

- **`default`** — the external, shared `nginx-proxy` network from [Traefik Dockerized](https://github.com/Devin345458/traefik-dockerized). Needed for Traefik routing and for the shared `mysqldb8` / `redis` containers.
- **`app`** — created per compose project (as `<project>_app`). Private to this stack.

Every stack on `nginx-proxy` registers its service names as DNS aliases there, and this template names its services `webserver` / `frontend`. That means `webserver` is **ambiguous on `nginx-proxy`** — Docker round-robins it across every project (and every git worktree of every project) currently up. The `app` network exists so service-to-service traffic resolves inside one project only.

Rule of thumb for a new service: put it on `app`, and add `default` **only** if it needs Traefik or the shared MySQL/Redis. `stripe-cli` is the worked example — it is deliberately kept off `nginx-proxy` so `--forward-to webserver:80` cannot deliver webhooks into a different client project's app.

### Local AMP

Point Apache/Nginx at `public/` if you can't use Docker. Not recommended — the rest of the team and CI run Docker.

## Common commands

All artisan/composer/npm commands run inside the webserver container. The container name is whatever you set `DOCKER_ROUTER` to in `.env`.

```sh
# Tests
docker compose exec $DOCKER_ROUTER composer ci                # pint --test + pest --parallel
docker compose exec $DOCKER_ROUTER ./vendor/bin/pest --filter=Auth

# Code style
docker compose exec $DOCKER_ROUTER composer format            # pint --parallel (auto-fix)

# DB
docker compose exec $DOCKER_ROUTER php artisan migrate
docker compose exec $DOCKER_ROUTER php artisan migrate:fresh --seed

# Regenerate frontend types from Laravel routes/models
docker compose exec $DOCKER_ROUTER composer typescript        # runs wayfinder:generate

# Scaffold a new Vue page (uses stubs/vue-make-page.stub)
docker compose exec $DOCKER_ROUTER php artisan vue:make-page

# Create a user from the CLI
docker compose exec $DOCKER_ROUTER php artisan user:create
```

`npm run dev` / `npm run build` run on the host (or in the `frontend` service if you'd rather).

## Architecture notes

- **Auth**: Sanctum. SPA cookie auth for web; bearer tokens (personal access tokens) for mobile/Capacitor. `/api/v1/auth` POST issues a token, GET returns the current user, DELETE logs out. Impersonation is supported via `POST /auth/impersonate`.
- **File pipeline**: `app/Models/File.php` + `app/Http/Controllers/FileController.php` handle upload, sized variant URLs, signed download, view, and destroy. S3-backed in non-local envs.
- **WhoDidIt audit trail**: `app/Traits/WhoDidIt.php` + `WhoDidItMixin` adds `created_by` / `updated_by` to any model that uses the trait. Schema helper available via `$table->whoDidIt()`.
- **Router DSL**: `resources/ts/router/` has a custom `RouteDesigner` + `RouteBuilder` + `RouteGroup` API on top of vue-router. See `router/index.ts` for examples.
- **Wayfinder TS types**: Backend route/controller signatures are converted to TS at `resources/ts/types/laravel/` via `composer typescript`. Run after changing routes/controllers; frontend calls then have proper typings.
- **Versioning plugin**: `resources/ts/plugins/versioning/` ships a Vite plugin + a Vue plugin that detects new deploys and prompts the user to reload — paired with `UpdateDetector.vue`.
- **Sentry**: Wired both Laravel-side (config/sentry.php + `app/Exceptions/Sentry.php`) and Vue-side (main.ts + vite plugin uploads sourcemaps). A `sentry_logs` log channel exists in `config/logging.php`.

## Deployment

GitHub Actions, via the [`dev-applied/deploy-action`](https://github.com/dev-applied/deploy-action) reusable workflow. Workflows in `.github/workflows/`:

- `ci.yml` — Pint + Pest parallel on every push (currently `workflow_dispatch` only in the template; enable on PR/push when bootstrapping a real project)
- `deploy-dev.yml`, `deploy-staging.yml`, `deploy-production.yml`, `deploy-releases.yml` — one per environment
- `deploy-lambda.yml` — serverless deploys (see `servers/serverless/`)
- `dependabot-auto-merge.yml`, `dependabot-labels.yml` — dependabot handling

## For AI agents

Claude Code agents working in this repo should also read `CLAUDE.md` at the project root and the area-specific `CLAUDE.md` files under `resources/ts/`, `app/`, and `database/`. Key rules:

- Never run artisan / composer / npm on the host — always `docker compose exec $DOCKER_ROUTER …`.
- Never hardcode hex / rgba colors in SCSS — use Vuetify CSS vars / theme tokens.
- Never mock the DB in integration tests — use a real DB (Sqlite in-memory is fine for `tests/Unit`).
- When changing a route or controller, run `composer typescript` so frontend types stay in sync.
- Pages and components are Vue 3 + Options API + `defineComponent`. `<script setup>` migration is tracked as a separate effort — don't mix paradigms in one PR.
- `this.$auth`, `this.$http`, `this.$error`, `this.$routeTo`, `this.$confirm`, `this.ROUTES` come from plugins in `resources/ts/plugins/` — read those before guessing API shapes.

If something in this README contradicts what's actually in `composer.json` / `package.json` / the codebase, trust the code and open a PR fixing the README.
