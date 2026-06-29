/**
 * Route name constants — a DEPENDENCY-FREE leaf module.
 *
 * WHY this is separate from `paths.ts`:
 * `paths.ts` performs the route *registration* (`RouteDesigner.group(...)`),
 * and its registration only lands in the table when `index.ts` calls
 * `RouteDesigner.compile()`. Several early-loaded modules (`plugins/index.ts`,
 * `plugins/axios.ts`) only need the route *names*. If they import those names
 * from `paths.ts`, they drag the whole registration module into the plugin
 * graph BEFORE `@/router` is imported. `paths.ts` then transitively imports the
 * `Guest`/`Authorization` middleware -> `routeTo` -> `@/router` (index.ts),
 * which runs `compile()` while `paths.ts` is still mid-import — so the table is
 * compiled EMPTY and the whole app renders blank. (Manifests under the Vuetify-4
 * stack, whose auto-import forces a first-load dep re-optimization + reload that
 * re-instantiates the module graph in the broken order.)
 *
 * Keeping the names in this leaf (zero imports) means importing a route name
 * never triggers `paths.ts`, so registration always completes before compile().
 *
 * Use with `this.$routeTo(this.ROUTES.X)` — never inline route-name strings.
 */
export const ROUTES = {
  LOGIN: "login",
  REGISTER: "register",
  SET_PASSWORD: "set-password",

  DASHBOARD: "dashboard",
  TEST: "test",

  ITEMS_LIST:   "items.list",
  ITEMS_CREATE: "items.create",
  ITEMS_EDIT:   "items.edit",
}
