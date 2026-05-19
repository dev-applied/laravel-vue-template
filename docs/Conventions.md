# Canonical Resource Conventions

This document extracts the patterns demonstrated by the `Item` resource into a checklist for building any new resource on this stack. The actual files referenced here are the canonical implementations — copy them as starting points and adapt.

> **AI agents:** treat this as the source-of-truth pattern. When implementing a new feature that adds a resource, mirror these files step for step. The patterns are deliberate; deviating without a reason is the most common source of bugs that QA catches.

## The 12-layer flow

Every new resource follows this sequence:

| # | Layer | Reference file | Purpose |
|---|---|---|---|
| 1 | **Migration** | `database/migrations/*_create_items_table.php` | Schema. Always use `$table->whoDidIt()` + `$table->softDeletes()` for any user-editable entity. Index FKs and filter columns. |
| 2 | **Model** | `app/Models/Item.php` | Eloquent model. Always: `HasFactory`, `SoftDeletes`, `WhoDidIt` traits. Define `$fillable`, `$casts`, relations, and a `scopeFilter(array)` that inverts query-string filters. |
| 3 | **Enum** (if applicable) | `app/Enums/ItemStatus.php` | String-backed enum with `label()` and `options()` static for v-select consumption. Cast on the model. |
| 4 | **Factory** | `database/factories/ItemFactory.php` | Fake data + named-state helpers (`active()`, `ownedBy($id)`). Tests read more cleanly than inline `->state([…])` calls. |
| 5 | **FormRequests** | `app/Http/Requests/StoreItemRequest.php` + `UpdateItemRequest.php` | One per verb. Update uses `sometimes` for partial bodies. `authorize()` returns `true` — per-record auth lives in the Policy layer below, FormRequest is just for shape validation. |
| 6 | **Policy** | `app/Policies/ItemPolicy.php` | Per-record authorization. Laravel auto-resolves `App\Policies\<Model>Policy`. Baseline: any authenticated user can list/view/create; only the owner can update/delete. Adjust per real auth model (teams, roles, sharing). |
| 7 | **Resource** | `app/Http/Resources/ItemResource.php` | API envelope. ISO 8601 dates. `whenLoaded()` for relations. |
| 8 | **Controller** | `app/Http/Controllers/ItemController.php` | Thin: model query + FormRequest + Resource. Call `$this->authorizeResource(<Model>::class, '<param>')` in the constructor so the policy fires before every action. `vuetifyPaginate()` mixin (from `VuetifyPaginateMixin` in AppServiceProvider) emits the pagination shape the frontend `AppPaginationTable` expects. |
| 9 | **Route** | `routes/api.php` | One line: `Route::apiResource('items', ItemController::class)` inside the `auth:sanctum` group. |
| 10 | **TS types** (generated) | `resources/ts/types/laravel/` | Run `composer typescript` after backend changes — Wayfinder regenerates these from your routes + FormRequests. |
| 11 | **Vue list page** | `resources/ts/pages/items/ItemListPage.vue` | Options API. `AppPaginationTable` bound to `endpoint="items"` with a reactive `filters` prop. Slot overrides for non-string columns (chips, dates, related-record names, action buttons). |
| 12 | **Vue form page** | `resources/ts/pages/items/ItemFormPage.vue` | Options API. ONE component handles create + edit — branch on `$route.params.id`. `AppServerValidationForm` slot props (`submit`, `loading`, `getErrors`) plumb directly to Vuetify field `:loading` / `:error-messages`. |

## Tests (mandatory, not optional)

Every controller gets two Feature test files:

- `tests/Feature/<Resource>ControllerTest.php` — index + show paths (auth required, pagination shape, filters, eager-loaded relations, 404).
- `tests/Feature/<Resource>ControllerStoreUpdateDestroyTest.php` — mutations (validation rules, partial-update happy path, WhoDidIt-trait audit columns set correctly, soft-delete behavior).

The two `ItemControllerTest*.php` files are the canonical pattern. Run with `composer ci` (pint --test + pest --parallel) or `./vendor/bin/pest --filter Item`.

## The rules

- **Never put validation in a controller.** FormRequest or nothing.
- **Never return raw model arrays.** Resource or nothing. The envelope shape is a contract.
- **Never do query filtering inline in the controller action.** Add a `scopeFilter` on the model.
- **Always eager-load relations needed by the response.** Don't rely on `automaticallyEagerLoadRelationships()` masking N+1s in production — the warning is dev-only.
- **Always run `composer typescript` after changing a route, FormRequest, or controller signature.** Otherwise the frontend types lie.
- **Always wrap forms in `AppServerValidationForm`.** Manual `$http.post` + manual error display = inconsistent UX and missed 422 cases.
- **Always use the field-component family (`AppDateInput`, `AppAutoComplete`, `AppMaskField`, etc.) over raw Vuetify components** when one fits. They standardize date formats, async loading, masks — saves you from re-deriving each per project.

## Copying for a new resource

1. `php artisan make:migration create_things_table` — fill in fields, add `$table->whoDidIt(); $table->softDeletes();`.
2. `php artisan make:model Thing -f` — add traits + `scopeFilter`.
3. Maybe an enum in `app/Enums/`.
4. `php artisan make:request StoreThingRequest UpdateThingRequest` — copy from `StoreItemRequest`/`UpdateItemRequest`.
5. `php artisan make:policy ThingPolicy --model=Thing` — copy from `ItemPolicy`, adjust ownership/visibility rules to match the real auth model. Laravel auto-resolves by name; no provider registration needed.
6. `php artisan make:resource ThingResource` — copy from `ItemResource`.
7. `php artisan make:controller ThingController --api --requests` — but discard the boilerplate and copy from `ItemController`, including the `authorizeResource()` call in the constructor.
8. One line in `routes/api.php`.
9. `composer typescript` to regen frontend types.
10. Two Vue files in `resources/ts/pages/things/` — `ThingListPage.vue` and `ThingFormPage.vue`, copied from the Item versions.
11. Three lines in `router/paths.ts` (ROUTES + the three RouteDesigner.route calls).
12. Two Feature test files in `tests/Feature/` — including non-owner 403 specs to prove the Policy is wired up.

If a step in this list is genuinely not applicable (e.g. the entity has no enum), skip it. Don't skip a step because it feels like overhead — every step exists because something broke without it in a previous project.

## See also

- `docs/Capacitor.md` — mobile-target opt-in (when applicable).
- `tests/Feature/ItemControllerTest.php` — Pest 4 test patterns with Sanctum auth + factories.
- `app/CLAUDE.md`, `resources/ts/CLAUDE.md`, `database/CLAUDE.md` — area-specific conventions (after the P0 cleanup PR merges).
