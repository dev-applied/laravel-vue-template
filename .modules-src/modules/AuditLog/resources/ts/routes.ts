import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  AUDIT_LOG: "audit-log.index",
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/audit-log",
    () => import("@modules/AuditLog/resources/ts/pages/AuditLogPage.vue"),
    ROUTES.AUDIT_LOG
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
