import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  SETTINGS: "settings.index",
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/settings",
    () => import("@modules/Settings/resources/ts/pages/SettingsPage.vue"),
    ROUTES.SETTINGS
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
