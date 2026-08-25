import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  USERS: "users.index",
}

RouteDesigner.group('', function () {
  // Deliberately NOT `.addPermissionAny('users.manage')`.
  //
  // The client-side permission check reads `all_permissions` off the auth
  // payload, and that key is contributed by the RolesPermissions module. With
  // RolesPermissions absent the list is empty, every permission check fails,
  // and a gated route bounces EVERYONE to the dashboard — the module would be
  // broken out of the box for any project that installed it on its own.
  //
  // The API is gated on `can:manage-users` regardless, so this is not a hole:
  // an unauthorised visitor reaches the screen and the table shows the 403.
  // Projects running RolesPermissions should add the gate back — see README.
  RouteDesigner.route(
    "/users",
    () => import("@modules/Users/resources/ts/pages/UsersPage.vue"),
    ROUTES.USERS
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
