import RouteDesigner from "@/router/RouteDesigner"
import {KERNEL_ROUTES} from "@/router/kernel-routes"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

// The KERNEL's name, not one of our own. DASHBOARD is a kernel route-name
// contract — Guest.ts sends signed-in users there and the 404 page links to it
// — so redefining it as "dashboard.index" did not add a route, it RENAMED the
// contract out from under the kernel. Nothing was left registered under
// "dashboard", and paths.ts then registered its placeholder under our name.
export const ROUTES = {
  DASHBOARD: KERNEL_ROUTES.DASHBOARD,
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/dashboard",
    () => import("@modules/Dashboard/resources/ts/pages/DashboardPage.vue"),
    ROUTES.DASHBOARD
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
