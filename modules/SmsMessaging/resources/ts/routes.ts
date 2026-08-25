import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  SMS_LOG: "sms.log",
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/sms-log",
    () => import("@modules/SmsMessaging/resources/ts/pages/SmsLogPage.vue"),
    ROUTES.SMS_LOG
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
