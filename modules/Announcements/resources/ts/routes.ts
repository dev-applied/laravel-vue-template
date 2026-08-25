import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  ANNOUNCEMENTS: "announcements.index",
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/announcements",
    () => import("@modules/Announcements/resources/ts/pages/AnnouncementsPage.vue"),
    ROUTES.ANNOUNCEMENTS
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
