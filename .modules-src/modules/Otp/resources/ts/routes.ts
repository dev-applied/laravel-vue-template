import RouteDesigner from "@/router/RouteDesigner"
import Guest from "@/middleware/Guest"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  OTP_LOGIN: "otp.login",
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/sign-in/code",
    () => import("@modules/Otp/resources/ts/pages/OtpLoginPage.vue"),
    ROUTES.OTP_LOGIN
  ).title("Sign in with a code")
})
  .layout("Empty")
  .middleware([ForceTypes, Guest])
  .props()
