# resources/ts/CLAUDE.md

Frontend conventions for this template. Read after the root `CLAUDE.md`.

## Style

- **Vue 3 + Options API + `defineComponent`.** Every page and component uses `<script lang="ts">` + `defineComponent({...})`. Don't mix `<script setup>` until the full migration is decided template-wide.
- **Composition-API code only lives in composables (`composables/`) and shared inner-component logic (e.g. `AppAutoComplete/use*.ts`).** Pages and most components consume composables imperatively through `this.$*` plugins or via `setup()` returning into the Options API instance.
- **TypeScript everywhere**: `lang="ts"` on every `<script>`, `tsconfig.json` is strict. Run `vue-tsc --noEmit` before committing if touching shared types.

## Folder roles

| Folder           | What lives here                                                                                       |
| ---------------- | ----------------------------------------------------------------------------------------------------- |
| `pages/`         | Route-level components. One default-export per route. Wrapped in `<v-container>`.                     |
| `layouts/`       | App chrome (header / nav / footer). `DefaultLayout`, `EmptyLayout`. Layouts are matched per-route.    |
| `components/`    | Reusable UI. `App*` prefix. Form inputs live in `components/fields/`.                                 |
| `composables/`   | Composition-API utilities consumable from both Options-API instances and other composables.           |
| `plugins/`       | Vue plugins that install global properties (`this.$auth`, etc.). One file = one global concern.       |
| `stores/`        | Pinia stores: `app`, `message`, `user`.                                                               |
| `middleware/`    | Router middleware (`Authentication`, `Authorization`, `Guest`, `ForceTypes`) — pipelined in routes.   |
| `mixins/`        | Vue 2-style mixins (e.g. `validators`). Used by Options-API pages; keep until script-setup migration. |
| `router/`        | Custom RouteDesigner/RouteBuilder DSL on top of vue-router.                                           |
| `types/`         | Manual TS types. `types/laravel/` is generated — don't edit by hand.                                  |
| `utils/`         | Pure helpers (`dayjs`, `convertToUnit`).                                                              |

## Global properties (Options API)

These come from plugins in `plugins/` and are available on every component via `this.$*`:

| `this.$*`         | Source              | Use for                                                                  |
| ----------------- | ------------------- | ------------------------------------------------------------------------ |
| `$auth`           | `plugins/auth.ts`   | `login`, `logout`, `user`, `permissions`. Backed by `composables/useAuth`.|
| `$http`           | `plugins/axios.ts`  | HTTP client. Auto-injects token, handles 401 → logout.                   |
| `$error`          | `errorHandler.ts`   | `(status, message, errors, surface = true) => boolean` — true if errored.|
| `$routeTo`        | `plugins/routeTo.ts`| Build a route object from a `ROUTES` enum.                               |
| `$confirm`        | `plugins/confirm`   | Promisified confirmation dialog.                                         |
| `$messages`       | `stores/message.ts` | Surface a snackbar message.                                              |
| `ROUTES`          | `router/paths.ts`   | Route name constants — use with `$routeTo`, never inline strings.        |

## Forms

Use `AppServerValidationForm` to wrap any `<v-form>` that POSTs to Laravel. It:

- Catches 422s and assigns errors to each field by name.
- Manages a `submitting` state you can bind to `:loading` on the submit button.
- Cleared by editing the field that errored.

Field components live in `components/fields/`. Use them by default over raw Vuetify:

| Field component       | When to use                                                       |
| --------------------- | ----------------------------------------------------------------- |
| `AppAutoComplete`     | Async/remote-search dropdowns. Has its own folder with composables.|
| `AppCombobox`         | Tags / multi-select with free-text.                                |
| `AppDateInput`        | Dates — standardizes display format and parsing.                   |
| `AppMaskField`        | Phone, SSN, anything with a mask.                                  |
| `AppFileDropzone`     | Drag-and-drop upload area.                                         |
| `AppFileUpload[Btn]`  | Single-button upload.                                              |
| `AppAddressField`     | Google Places-backed address autocomplete.                         |
| `AppPasswordValidation` | Password input with strength + rules display.                    |

## Adding a new component

- **Reusable across pages?** → `components/App<Name>.vue` (or `components/fields/App<Name>.vue` for inputs).
- **Single-page helper?** → keep it co-located inside the page file or in a `_components/` subfolder next to the page.
- **Cross-cutting state?** → Pinia store in `stores/`.
- **Cross-cutting effect?** → composable in `composables/`.

## Type generation

After changing routes or controllers backend-side:

```sh
docker compose exec $DOCKER_ROUTER composer typescript
```

Emits TS into `resources/ts/types/laravel/`. Use those types when calling APIs — don't `any` your way through it.

## Vuetify

- Theme is configured in `plugins/vuetify.ts`. Brand colors live there — change once, propagate everywhere.
- Use Vuetify utility classes (`d-flex`, `ga-4`, `text-primary`, `mb-5`) before reaching for custom SCSS.
- When you DO write SCSS, use `rgb(var(--v-theme-X))` — no hex, no rgba literals.

## Tests

`vitest` is installed but coverage is light. When you write a composable, add a sibling `.spec.ts`. Run with `npm run test` (add the script if missing).
