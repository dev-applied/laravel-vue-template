import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import ForceTypes from "@/middleware/ForceTypes"
import Guest from "@/middleware/Guest.ts"
import Authorization from "@/middleware/Authorization.ts"

export const ROUTES = {
  LOGIN: "login",
  REGISTER: "register",
  SET_PASSWORD: "set-password",

  DASHBOARD: "dashboard",
  TEST: "test",
}

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
  })
    .layout("Default")
    .middleware([Authorization])

})
  .layout("Empty")
  .middleware([ForceTypes, Authentication])
  .props()
