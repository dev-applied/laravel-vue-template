# Modules — full-stack, copy-in, firm-shared

The template supports **modules**: self-contained vertical slices (backend +
frontend) that live in `modules/<Name>/` and travel between projects by
**copy** from the firm modules repo (`dev-applied/laravel-vue-modules`) —
never by composer require, never from a registry. A module committed into a
client repo is that client's code: customize it freely.

This exists to stop rebuilding the same verticals (OTP auth, subscriptions,
exports, booking) from scratch. The pattern is lifted from washwerk's
`app/modules/` (34 modules in production) plus the four things its modules
never needed: self-containment, an in-module Vue half, a declared kernel
contract, and the distribution layer.

## Module anatomy

```
modules/Example/
├── module.json                  # manifest + version stamps (see below)
├── ModuleServiceProvider.php    # Modules\Example\ModuleServiceProvider
├── Http/
│   ├── Controllers/             # extend App\Http\Controllers\Controller
│   ├── Requests/                # FormRequests — all validation lives here
│   └── Resources/               # API resources (envelope conventions apply)
├── Models/                      # models point at their factory via newFactory()
├── Database/
│   ├── Migrations/              # loaded by the provider; reversible; never edited post-merge
│   └── Factories/               # Modules\Example\Database\Factories\*
├── Routes/api.php               # registered by the provider under api/v1
├── Tests/Feature/               # Pest, real DB — wired via phpunit.xml + tests/Pest.php
└── resources/ts/                # the Vue half (lowercase — mirrors the app root)
    ├── routes.ts                # registers routes + exports ROUTES constants
    └── pages/                   # Options API pages, lazy-imported by routes.ts
```

Naming: PHP dirs mirror `app/` (`Http/Controllers`, not washwerk's flat
`Controllers/`) — deliberately, and because `Resources/` (PHP) would collide
with `resources/` (frontend) on macOS's case-insensitive filesystem.

## How loading works (no package, no magic)

- **Autoload**: one PSR-4 line in composer.json — `"Modules\\": "modules/"`.
  Adding a module needs `composer dump-autoload`, nothing else.
- **Providers**: `App\Providers\ModuleLoaderServiceProvider` globs
  `modules/*/ModuleServiceProvider.php` once at boot and registers each. A
  directory IS the enable switch — there is no activator, no statuses file.
- **Frontend**: `router/paths.ts` eager-globs `/modules/*/resources/ts/routes.ts`.
  Importing a module's routes file registers its routes on RouteDesigner and
  merges its exported `ROUTES` constants into the app `ROUTES`.
- **Pages are lazy imports, never strings** — the string resolver only sees
  `resources/ts/pages/`. `() => import("@modules/X/resources/ts/pages/XPage.vue")`
  also code-splits per module.
- **Modules declare their own layout + middleware stack.** Module routes are
  registered outside the core groups in paths.ts and inherit nothing — a
  module's routes.ts group states `[ForceTypes, Authentication, Authorization]`
  (or its own variation) explicitly.

## The kernel contract

A traveling module may assume ONLY what every template project has:

- The base `Controller`, the response envelope, `AppException`, `WhoDidIt`,
  `vuetifyPaginate`, the File pipeline.
- Frontend: the `App*` component library, `components/fields/*`, the `$auth` /
  `$http` / `$error` / `$routeTo` / `$confirm` / `$messages` globals, the
  router middleware (`Authentication`, `Authorization`, `Guest`, `ForceTypes`),
  layouts `Default` / `Empty`.
- NOT `spatie/laravel-permission` (removed from the template), NOT any
  per-project package. If a module needs a package, `module.json` declares it
  in `composer_requires` and `scripts/module-add.sh` will surface it (the
  human runs the composer require — visible, not magic).

Anything else the module carries itself.

## module.json

```json
{
    "name": "Example",
    "version": "0.1.0",
    "harvested_from": "<repo the code was extracted from>",
    "template_version_tested": "2026-08",
    "description": "…",
    "installed_from_commit": "<stamped by module-add.sh>",
    "installed_at": "<stamped by module-add.sh>"
}
```

The stamps are the three-way-merge base pointer: an update is
`diff(upstream@installed_from_commit, upstream@HEAD)` applied onto the
client's (possibly customized) copy. Conflicts surface only where the client
actually diverged — that conflict resolution is the billable part of a module
update, and it's exactly what retainer/upgrade engagements price.

## Workflows

- **Add**: `php artisan module:add` (in the container, like every artisan
  command). With no arguments it multiselects from every module in the firm
  modules repo; pass names to skip the prompt (`module:add Otp Billing`).
  Source resolution: `--from=<local checkout>` wins; otherwise the GitHub API
  with `MODULES_GITHUB_TOKEN` (or `GITHUB_TOKEN`) from `.env` — a fine-grained
  PAT with read access to the modules repo. The command copies the module in,
  stamps `module.json` (`installed_from_commit`, `installed_at`), and runs
  `composer dump-autoload`; it prints the migrate / `route:clear` /
  `composer typescript` follow-ups.
- **Check drift**: `php artisan module:outdated` — notify-only table of local
  vs upstream versions. Applying updates is deliberate work, never automatic;
  do it on retainer touches and Laravel-major upgrades (upstream ports the
  module once, every client upgrade pulls the port).
- **Harvest** (feeds the modules repo): when a project builds something
  module-worthy — or the quote skill's Prior Art Check matches a family with
  2+ prior builds and no module — extract it as the FIRST build task of the
  funded project: genericize to template conventions, contribute upstream,
  then specialize for the client. AI drafts the extraction; the project dev
  reviews; template merges go through Devin.

## Authoring rules

1. **Options API only**, like every page in the template. Composition API only
   in composables.
2. **One root Vite build, forever.** Module frontends ride the app build via
   the globs. Never a per-module build, never a second output dir — that
   breaks the Vuetify singleton and Capacitor's static webDir.
3. **QA affordances ship WITH the module** (per the firm qa-affordances spec):
   a module with OTP carries its env-gated test bypass; a module with billing
   carries its entitlement-state seams. Fail-closed, non-prod only.
4. **Route names are namespaced** (`example.notes`) and exported from
   routes.ts; pages/components prefix with the module name where collision is
   plausible.
5. **Migrations are append-only** once a module has shipped anywhere.
6. **The Wayfinder gotcha**: Wayfinder generates from the CACHED route table
   when one exists. After adding/removing a module, run
   `php artisan route:clear` before any `npm run build` / `composer
   typescript`, or module routes silently vanish from the generated TS.

## Removing the reference module

`modules/Example` exists to prove the wiring and as the copy-me shape. Client
projects that don't want it: `rm -rf modules/Example && composer dump-autoload`
— nothing else references it.
