import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  ROLES: "roles.index",
}

RouteDesigner.group('', function () {
  // permissionsAny is enforced by middleware/Authorization.ts against
  // $auth.hasAnyPermissions — which reads the all_permissions this module's
  // HasAccessControl trait appends to the user payload.
  RouteDesigner.route(
    "/roles",
    () => import("@modules/RolesPermissions/resources/ts/pages/RolesPage.vue"),
    ROUTES.ROLES
  ).addPermissionAny("roles.manage")
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
