import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  ONBOARDING: "onboarding.index",
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/onboarding",
    () => import("@modules/Onboarding/resources/ts/pages/OnboardingPage.vue"),
    ROUTES.ONBOARDING
  )
})
  .layout("Default")
  // Authentication only. The one screen that releases a gated user must never
  // sit behind the gate — see RequireOnboarding for the lock-out this avoids.
  .middleware([ForceTypes, Authentication])
  .props()
