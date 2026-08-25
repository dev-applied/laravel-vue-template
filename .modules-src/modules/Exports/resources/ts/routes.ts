import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  EXPORTS: "exports.index",
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/exports",
    () => import("@modules/Exports/resources/ts/pages/ExportsPage.vue"),
    ROUTES.EXPORTS
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
