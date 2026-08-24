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

RouteDesigner.group('', function () {

  // Guest Routes (login / set-password moved to modules/Auth)
  RouteDesigner.group('', function () {
    RouteDesigner.route("/test", "TestPage", ROUTES.TEST)
  })
    .layout("Empty")
    .middleware([Guest])

  // Authorized routes
  RouteDesigner.group('', function () {
    RouteDesigner.route("/dashboard", "DashboardPage", ROUTES.DASHBOARD)

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
