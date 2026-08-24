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
├── Mail/                        # Mailables the module sends (optional)
├── Mcp/                         # MCP server + tools (optional; see the Auth module)
├── Console/Commands/            # module artisan commands (optional)
├── Database/
│   ├── Migrations/              # loaded by the provider; reversible; never edited post-merge
│   └── Factories/               # Modules\Example\Database\Factories\*
├── Routes/api.php               # registered by the provider under api/v1
├── Tests/Feature/               # Pest, real DB — wired via phpunit.xml + tests/Pest.php
├── resources/
│   ├── views/                   # Blade (mail, server-rendered pages) — loadViewsFrom('<slug>')
│   └── ts/                      # the Vue half (lowercase — mirrors the app root)
│       ├── routes.ts            # registers routes + exports ROUTES constants
│       └── pages/               # Options API pages, lazy-imported by routes.ts
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
- **Plugins**: a module MAY ship `resources/ts/plugin.ts` with a default export
  taking the Vue app. `plugins/index.ts` eager-globs
  `/modules/*/resources/ts/plugin.ts` and calls each one AFTER the kernel
  plugins, so a module can register a global property, a Vue plugin, or a mixin
  and still rely on `$http` / `$auth` / `$error`. `modules/Files` registers
  `$file` this way. A module that needs to augment the global property types
  ships its own `.d.ts` — `tsconfig.json` already globs `modules/**/*.ts`.
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
  `vuetifyPaginate`.
- Frontend: the `App*` component library, `components/fields/*`, the `$auth` /
  `$http` / `$error` / `$routeTo` / `$confirm` / `$messages` globals, the
  router middleware (`Authentication`, `Authorization`, `Guest`, `ForceTypes`),
  layouts `Default` / `Empty`.
- NOT `spatie/laravel-permission` (removed from the template), NOT the File
  pipeline (extracted to `modules/Files` 2026-08-24 — a module that uploads
  files must say so, and a project needs `module:add Files` for
  `AppFileUpload` / `AppFileDropzone` / `AppLightBoxImage` / `$file` to exist),
  NOT any per-project package. A module declares the packages it always needs in
  `module.json` `composer_requires` (and option-specific packages under an
  option choice's `require` — see Options below); `module:add` runs the
  `composer require` for it.

Anything else the module carries itself.

### Route-name contract

The kernel navigates to a few routes **by name** but does not register them
itself — a module must. These live in `resources/ts/router/kernel-routes.ts`
(an import-free file, so both `paths.ts` and a module's `routes.ts` can import
it without the eager-glob import cycle):

- **`LOGIN`** — `Authorization.ts` bounces guests here with a deep link,
  `axios.ts` redirects 401s here, `Guest.ts` sends logged-in users to
  `DASHBOARD`. The **Auth module registers it.** A project without the Auth
  module must register a route named `KERNEL_ROUTES.LOGIN` itself.
- **`DASHBOARD`** — registered by the kernel (`paths.ts`).

A module that satisfies a kernel route-name contract re-exports the constant
from its `routes.ts` (Auth exports `LOGIN`) so `paths.ts` merges it back into
the app `ROUTES`.

## module.json

```json
{
    "name": "Example",
    "version": "0.1.0",
    "harvested_from": "<repo the code was extracted from>",
    "template_version_tested": "2026-08",
    "description": "…",
    "composer_requires": ["vendor/pkg:^1.0"],
    "npm_requires": ["some-pkg@^1.0.0"],
    "options": { "…": "see Options below" },
    "installed_from_commit": "<stamped by module:add>",
    "installed_at": "<stamped by module:add>",
    "installed_options": { "…": "the choices made at install (stamped by module:add)" }
}
```

The stamps are the three-way-merge base pointer: an update is
`diff(upstream@installed_from_commit, upstream@HEAD)` applied onto the
client's (possibly customized) copy. Conflicts surface only where the client
actually diverged — that conflict resolution is the billable part of a module
update, and it's exactly what retainer/upgrade engagements price.
`installed_options` records the option answers so an update replays them (and
`module:configure` can change them) — see Options.

## Options (variants)

A module can offer install-time choices — "Sanctum or Sanctum + OAuth?",
"which OTP vendor?" — so one module covers variants that mostly overlap. An
option's answer **prunes files, adds only the composer deps it needs, sets
`.env` keys, and runs post-install commands**. The chosen answers are stamped
into the installed `module.json` as `installed_options`.

Authoring — `module.json` `options`:

```jsonc
"options": {
  "<key>": {
    "prompt": "Human question",
    "type": "select" | "multiselect" | "confirm",
    "default": "<choiceKey>" | ["<choiceKey>", …] | true,
    "choices": {                       // select / multiselect
      "<choiceKey>": {
        "label": "Human label",
        "drop":        ["glob", …],    // files/dirs removed when this choice is active
        "require":     ["pkg:^ver"],   // composer require (auto-run)
        "require_dev": ["pkg:^ver"],
        "npm":         ["pkg@^ver"],   // npm install (auto-run where node exists)
        "npm_dev":     ["pkg@^ver"],
        "env":         {"KEY": "value"},
        "run":         ["artisan cmd"] // post-install, run as a fresh subprocess
      }
    }
  }
}
```

- `type: confirm` uses `choices: {"true": {…}, "false": {…}}`.
- `drop` globs: `Dir/**` (whole tree), `Dir/*.php` (one level), or a literal
  path. Drop the *other* variants; there is no `keep`.
- **select** applies one choice's effects; **multiselect** the union.
- `modules/Auth` is the reference: `auth = sanctum | sanctum+oauth`, where the
  oauth choice requires `laravel/passport`, sets `AUTH_OAUTH_ENABLED`, and runs
  `auth:enable-oauth`, while the sanctum choice drops the OAuth files.

## Workflows

## Workflows

- **Add**: `php artisan module:add` (in the container, like every artisan
  command). With no arguments it multiselects from every module in the firm
  modules repo (`dev-applied/laravel-vue-modules`); pass names to skip the
  prompt (`module:add Otp Billing`). Source resolution: `--from=<local
  checkout>` wins; otherwise the GitHub API with `MODULES_GITHUB_TOKEN` (or
  `GITHUB_TOKEN`) from `.env` — a fine-grained PAT with read access to the
  modules repo. The command copies the module in, prompts any `options`
  (`--option key=value` presets a choice; `--no-interaction` takes defaults),
  installs the module's composer deps (base + the chosen options' — `composer
  require` runs automatically; `--no-install-deps` prints it instead), runs the
  options' post-install hooks, stamps `module.json`
  (`installed_from_commit`, `installed_at`, `installed_options`), and runs
  `composer dump-autoload`; it prints the migrate / `route:clear` /
  `composer typescript` follow-ups.
- **Reconfigure**: `php artisan module:configure <Name>` — re-prompts an
  installed module's options (pre-filled with the current answers) and makes
  the file set match: files a newly-selected choice needs are fetched back
  from a pristine source copy, files it drops are removed, composer deps and
  `.env` are swapped, hooks run. Existing files are never overwritten, so
  customizations survive. Needs source access (`--from` / token), same as add.
- **Check drift**: `php artisan module:outdated` — notify-only table of local
  vs upstream versions. Applying updates is deliberate work, never automatic;
  do it on retainer touches and Laravel-major upgrades (upstream ports the
  module once, every client upgrade pulls the port). Updates replay the
  module's `installed_options` so a pruned variant stays scoped. (The automated
  update/merge command itself is not built yet — updates today are the manual
  three-way merge described above.)
- **Harvest** (feeds the modules repo): when a project builds something
  module-worthy — or the quote skill's Prior Art Check matches a family with
  2+ prior builds and no module — extract it as the FIRST build task of the
  funded project: genericize to template conventions, contribute upstream,
  then specialize for the client. AI drafts the extraction; the project dev
  reviews; template merges go through Devin.

## Authoring rules

1. **Pages and layouts use the Options API** (`defineComponent`), like every
   page in the template — they depend on the `this.$*` globals. Leaf
   components may use `<script setup>`, which is what most of the kernel
   component library does. Never mix both styles in one file.
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
