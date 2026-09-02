# app/CLAUDE.md

Backend (Laravel) conventions for this template. Read after the root `CLAUDE.md`.

## Stack & posture

- Laravel 13, PHP ^8.4, `declare(strict_types=1);` at the top of every file.
- `Model::automaticallyEagerLoadRelationships()` is enabled in `AppServiceProvider` — N+1s log a warning in dev. Don't disable it.
- `Http::preventStrayRequests($this->app->runningUnitTests())` — tests must mock external HTTP explicitly.
- `DB::prohibitDestructiveCommands($this->app->isProduction())` — `migrate:fresh` etc. blocked in prod.

## Folder roles

| Folder            | What lives here                                                                                  |
| ----------------- | ------------------------------------------------------------------------------------------------ |
| `Console/Commands/` | Custom artisan commands. `MigrateCommand`, `UserCommand`, `Vue\MakePageCommand`, `Vue\RouteListCommand`. |
| `Exceptions/`     | `AppException` (user-facing 4xx errors), `Sentry` (handler hook).                                 |
| `Http/Controllers/` | Thin — delegate to Services / FormRequests / Resources. `FileController`, `UserController`. (Auth + forgot-password controllers ship in the **Auth module**, added via `php artisan module:add Auth`.) |
| `Http/Resources/` | API response shape. `AuthUserResource`.                                                           |
| `Interfaces/`     | Contracts. `WithSelected`.                                                                        |
| `Mail/`           | Mailables. (Empty by default — `ForgotPasswordMail` moved to `modules/Auth`.)                     |
| `Mixins/`         | Eloquent/Builder/Blueprint mixins registered in `AppServiceProvider::configureMixins()`: `HasManyMixin`, `VuetifyPaginateMixin`, `WhoDidItMixin`. |
| `Models/`         | `User`, `File`.                                                                                   |
| `Providers/`      | `AppServiceProvider`. Read it before adding new global behavior.                                  |
| `Rules/`          | Custom validation rules. `BulkExists` avoids N+1 on bulk existence checks.                        |
| `Services/`       | Business logic services. Note: `SnsHogService` / `TwilioHogService` are **inert extension points**, not active — see their docblocks. |
| `Traits/`         | Cross-model traits. `WhoDidIt` (audit), `WithSelected` (pagination-while-keeping-selection).     |

## Auth

- **Auth is a module, not in the template by default.** `php artisan module:add Auth` pulls it from the firm modules repo (choose Sanctum, or Sanctum + Passport OAuth). It provides login/me/logout/impersonation/forgot-password, the `/mcp` endpoint, and the optional OAuth layer; its routes register under `api/v1`. The bare `routes/api.php` holds only users/items/files, so a fresh template has no login until the module is added. See `docs/Authentication.md`.
- Sanctum. SPA cookie auth (web) + bearer tokens (mobile/Capacitor). **The default guard is `web`** — routes outside `auth:sanctum` middleware must resolve users via `$request->user('sanctum')` or bearer tokens read as guests (this bit `AuthController::me()` once; fixed 2026-08-24).
- The `User` model keeps Sanctum's `HasApiTokens`. If the Auth module's OAuth option is chosen, Passport issues its own tokens (own tables, the dormant `api` guard defined in `config/auth.php`) — do NOT add Passport's `HasApiTokens` to `User`; the traits collide and the OAuth flow doesn't need it.
- `App\Models\User` does NOT use `Spatie\Permission\Traits\HasRoles` — the package was deliberately removed Dec 2025. If a project needs roles, re-add per project.

## Controllers

- Keep thin. Inject FormRequest, dispatch to Service, return Resource.
- Use `apiResource('users', UserController::class)` style.
- For 404s on the API surface, the route file ends with `Route::fallback(fn () => response()->json(['message' => 'Not Found'], 404));` — don't add per-controller 404 handling.

## Models

- `automaticallyEagerLoadRelationships()` is on globally — don't add eager-loading just to silence the warning, fix the query.
- `User` is the canonical example. `protected $appends = ['full_name']` shows the Attribute cast pattern.
- For `created_by` / `updated_by` audit columns: add `use WhoDidIt;` on the model, `$table->whoDidIt();` in the migration.

## Migrations

- `declare(strict_types=1);` always.
- Use the `$table->whoDidIt()` macro (from `WhoDidItMixin`) when adding audit columns instead of writing the foreign keys by hand.
- Migration file names start with the date; the artisan generator handles this — don't hand-edit timestamps.

## Validation

- `App\Rules\BulkExists` — use for "validate that each value in this array exists in table X" without firing N queries.
- Use FormRequest classes for any endpoint accepting input — keeps controllers thin and 422 responses consistent.

## Tests

- Pest 4 (`pestphp/pest` + `pestphp/pest-plugin-type-coverage`).
- `modules/Example/Tests/Feature/ExampleNotesTest.php` is the in-repo canonical example (module tests run via the `Modules` testsuite in `phpunit.xml`); the Auth module carries `AuthTest`/`McpTest`/`OAuthTest` once added. `tests/CreatesApplication.php` + `tests/Pest.php` configure the suite.
- `composer ci` runs `pint --test && pest --parallel`. Run it before opening a PR.
- Don't mock the DB in Feature tests. Use `RefreshDatabase`.

## Sentry

- Config in `config/sentry.php`. `app/Exceptions/Sentry.php` hooks the exception handler.
- A `sentry_logs` log channel exists in `config/logging.php` — use it for breadcrumbs that shouldn't trigger an event.

## Adding a new endpoint

1. Define the route in `routes/api.php` under the appropriate middleware group.
2. Generate controller + FormRequest + Resource.
3. Run `composer typescript` to regenerate the Wayfinder TS types.
4. Write the Feature test using an existing module's `Tests/Feature` as the template.
