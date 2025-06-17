import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization"
import ForceTypes from "@/middleware/ForceTypes"

RouteDesigner.setNotFound("Error404Page", { layout: "Empty" })

export const ROUTES = {
  LOGIN: "login",
  SET_PASSWORD: "set-password",
  DASHBOARD: "dashboard",
  HOME: "home",
  TEST: "test"
}

RouteDesigner.group({ middleware: [ForceTypes, Authentication] }, function() {
  RouteDesigner.group({ layout: "Empty" }, function() {
    RouteDesigner.route("/test", "TestPage", ROUTES.TEST)
    RouteDesigner.route("/", "LoginPage", ROUTES.LOGIN)
    RouteDesigner.route("/home", "HomePage", ROUTES.HOME)
    RouteDesigner.route('set-password/:token', 'SetPasswordPage', ROUTES.SET_PASSWORD).passProps()
  })

  RouteDesigner.group({ middleware: [Authorization], layout: "Default" }, function() {
    RouteDesigner.route("/dashboard", "DashboardPage", ROUTES.DASHBOARD)
  })
})
