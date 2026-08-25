import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import ForceTypes from "@/middleware/ForceTypes"
import Guest from "@/middleware/Guest.ts"
import Authorization from "@/middleware/Authorization.ts"
import {KERNEL_ROUTES} from "@/router/kernel-routes"

export const ROUTES = {
  // LOGIN + DASHBOARD — names the kernel navigates to; see kernel-routes.ts.
  // LOGIN's route itself is registered by modules/Auth.
  ...KERNEL_ROUTES,

  TEST: "test",

  ITEMS_LIST:   "items.list",
  ITEMS_CREATE: "items.create",
  ITEMS_EDIT:   "items.edit",
}

RouteDesigner.setNotFound("Error404Page").layout('Empty')

// ─── Module routes ─────────────────────────────────────────────────────────
// Every module ships modules/<Name>/resources/ts/routes.ts. Importing it
// registers the module's routes against RouteDesigner (each module declares
// its OWN layout + middleware — nothing is inherited from the core groups
// below) and its exported ROUTES constants are merged into the app ROUTES so
// `this.$routeTo(this.ROUTES.X)` works for module pages too. The glob is
// build-time: dropping a module directory in (or deleting it) is picked up on
// the next dev-server restart / build with zero config edits.
const moduleRouteFiles = import.meta.glob<{ ROUTES?: Record<string, string> }>(
  '/modules/*/resources/ts/routes.ts',
  {eager: true}
)
for (const moduleRoutes of Object.values(moduleRouteFiles)) {
  Object.assign(ROUTES, moduleRoutes.ROUTES ?? {})
}

/**
 * Does an installed module already register this route name?
 *
 * The kernel ships fallback pages for the routes it depends on (DASHBOARD), and
 * a module is entitled to replace one — that is the point of a vertical slice.
 * It cannot do so by simply registering the same name, though: Vite compiles an
 * eager `import.meta.glob` into STATIC imports, which are hoisted, so every
 * module's routes.ts runs before a single line of this file. The kernel's own
 * registration therefore always lands second and wins the name map, which is
 * how the whole Dashboard module came to be inert — its page was registered,
 * then immediately shadowed by the placeholder below.
 *
 * So the kernel yields instead of racing: if a module exports the name, the
 * module owns the route.
 */
const moduleProvides = (name: string): boolean =>
  Object.values(moduleRouteFiles)
    .some((mod) => Object.values(mod.ROUTES ?? {}).includes(name))

RouteDesigner.group('', function () {

  // Guest Routes (login / set-password moved to modules/Auth)
  RouteDesigner.group('', function () {
    RouteDesigner.route("/test", "TestPage", ROUTES.TEST)
  })
    .layout("Empty")
    .middleware([Guest])

  // Authorized routes
  RouteDesigner.group('', function () {
    // Fallback only. modules/Dashboard replaces this with the real thing;
    // without the guard the placeholder shadowed it. See moduleProvides above.
    if (!moduleProvides(ROUTES.DASHBOARD)) {
      RouteDesigner.route("/dashboard", "DashboardPage", ROUTES.DASHBOARD)
    }

    RouteDesigner.route("/items",         "items/ItemListPage", ROUTES.ITEMS_LIST)
    RouteDesigner.route("/items/new",     "items/ItemFormPage", ROUTES.ITEMS_CREATE)
    RouteDesigner.route("/items/:id/edit","items/ItemFormPage", ROUTES.ITEMS_EDIT)
  })
    .layout("Default")
    .middleware([Authorization])

})
  .layout("Empty")
  .middleware([ForceTypes, Authentication])
  .props()
