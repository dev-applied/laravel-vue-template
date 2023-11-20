import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization"
import ForceTypes from "@/middleware/ForceTypes"

RouteDesigner.setNotFound("Error404", { layout: "Empty" })

export const ROUTES = {
  LOGIN: "login",
  DASHBOARD: "dashboard"
} as const

RouteDesigner.group({ middleware: [ForceTypes, Authentication] }, function() {
  RouteDesigner.group({ layout: "Empty" }, function() {
    RouteDesigner.route("/", "LoginPage", ROUTES.LOGIN)
  })

  RouteDesigner.group({ middleware: [Authorization], layout: "Default" }, function() {
    RouteDesigner.route("/dashboard", "DashboardPage", ROUTES.DASHBOARD)
  })
})
