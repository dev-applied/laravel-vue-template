import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import ForceTypes from "@/middleware/ForceTypes"
import Guest from "@/middleware/Guest.ts"
import Authorization from "@/middleware/Authorization.ts"
import { ROUTES } from "@/router/route-names"

// Names live in a dependency-free leaf so importing them never drags this
// registration module into the early plugin graph (which compiled the table
// empty -> blank app). Re-exported for any consumer that still imports `ROUTES`
// from here. See ./route-names.ts.
export { ROUTES }

RouteDesigner.setNotFound("Error404Page").layout('Empty')

RouteDesigner.group('', function () {

  // Guest Routes
  RouteDesigner.group('', function () {
    RouteDesigner.route("/login", "LoginPage", ROUTES.LOGIN)
    RouteDesigner.route("/test", "TestPage", ROUTES.TEST)
    RouteDesigner.route("/set-password", "SetPasswordPage", ROUTES.SET_PASSWORD)
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
