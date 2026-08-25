# resources/ts/CLAUDE.md

Frontend conventions for this template. Read after the root `CLAUDE.md`.

## Style

- **Pages and layouts: Options API.** Every page and layout uses `<script lang="ts">` + `defineComponent({...})` — they lean on the `this.$*` globals (`$http`, `$auth`, `$routeTo`, `$confirm`), which `<script setup>` cannot reach.
- **Leaf components: `<script setup>`.** Most of `components/` is `<script lang="ts" setup>` with typed `defineProps` / `defineEmits`. Presentational components take props and emit events rather than touching the globals, so they don't need the Options instance. Never mix both styles in one file.
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
| `ROUTES`          | `router/paths.ts`   | Route name constants — use with `$routeTo`, never inline strings. Names the kernel navigates to (`LOGIN`, `DASHBOARD`) live in `router/kernel-routes.ts`; `LOGIN`'s route is registered by `modules/Auth`. |

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
| `AppAddressField`     | Google Places-backed address autocomplete.                         |
| `AppPasswordValidation` | Password input with strength + rules display.                    |

## Adding a new component

- **Reusable across pages?** → `components/App<Name>.vue` (or `components/fields/App<Name>.vue` for inputs).
- **Single-page helper?** → keep it co-located inside the page file or in a `_components/` subfolder next to the page.
- **Cross-cutting state?** → Pinia store in `stores/`.
- **Cross-cutting effect?** → composable in `composables/`.

## Modules (frontend half)

Modules under `modules/<Name>/resources/ts/` plug into this app via globs in
`router/paths.ts` — see `docs/modules.md`. Rules that differ from app pages:

- Module `routes.ts` registers on RouteDesigner with **lazy-import pages**
  (`() => import("@modules/…/pages/X.vue")`) — string page names only resolve
  against `resources/ts/pages/`.
- Module routes declare their **own** layout + middleware stack; they inherit
  nothing from the core groups.
- Module pages/components follow every rule in this file (Options API,
  App* kernel components, no hex colors). One root Vite build only — never a
  per-module build.
- `@modules/*` alias maps to `modules/*` (vite + tsconfig).

## Type generation

After changing routes or controllers backend-side:

```sh
docker compose exec webserver composer typescript
```

Emits TS into `resources/ts/types/laravel/`. Use those types when calling APIs — don't `any` your way through it.

## Vuetify

- Theme is configured in `plugins/vuetify.ts`. Brand colors live there — change once, propagate everywhere.
- Use Vuetify utility classes (`d-flex`, `ga-4`, `text-primary`, `mb-5`) before reaching for custom SCSS.
- When you DO write SCSS, use `rgb(var(--v-theme-X))` — no hex, no rgba literals.

## Tests

`vitest` + `@vue/test-utils`. Coverage is light — when you write a composable, add a sibling `.spec.ts`; component specs go in `__tests__/` beside the component.

- **Run**: `npm run test` (watch) or `npm run test:ci` (once). CI runs `test:ci`.
- **Environment**: jsdom is not the default. A spec that touches the DOM needs `// @vitest-environment jsdom` on its first line.
- **Mounting**: `mount()` from `@vue/test-utils`. Do not hand-roll `createApp(...).mount(el)` — it works, but you lose `find`, `trigger`, `setProps` and the automatic teardown.
- **Vuetify components**: mounting one needs the Vuetify plugin in `global.plugins`. Prefer testing your own logic against a stub over mounting a whole Vuetify tree.
- Assert on behaviour, not on the rendered class soup. `resources/ts/components/__tests__/slot-forwarding.spec.ts` is the model: it reproduces the actual bug, proves the guard fixes it, and pins the guard so a future "simplification" fails the suite.

## Icons — Material, not MDI

Vuetify is configured with the **`md` iconset** (`vuetify/iconsets/md` +
`material-design-icons-iconfont`), so icon names are Material Icons **ligatures**:
`delete`, `add`, `expand_more`, `visibility_off`. Never `mdi-*`.

Material Icons is a ligature font, which is what makes this trap quiet: an
unrecognised name does not error, does not warn, and does not render a
placeholder — the font simply draws the string. `icon="mdi-delete-outline"`
paints the literal text *mdi-delete-outline*, about 280px wide where a 24px
glyph belongs. Tests pass, the console is clean, and the accessibility tree
looks plausible because the name IS the text content of a real ligature icon.
Only a screenshot shows it.

Vuetify's own docs and most snippets on the internet assume the `mdi` set, so
copied markup is the usual source. `bin/lint` in the modules repo greps for
`mdi-` for exactly this reason.
