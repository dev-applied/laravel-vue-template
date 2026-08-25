import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  NOTIFICATIONS: "notifications.index",
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/notifications",
    () => import("@modules/Notifications/resources/ts/pages/NotificationsPage.vue"),
    ROUTES.NOTIFICATIONS
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
