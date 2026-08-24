import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import ForceTypes from "@/middleware/ForceTypes"
import Guest from "@/middleware/Guest.ts"
import {KERNEL_ROUTES} from "@/router/kernel-routes"

// LOGIN is a kernel route-name contract (Authorization.ts / axios.ts navigate
// to it by name) — the kernel names it, this module registers it.
// SET_PASSWORD is module-private; the reset email links to its PATH
// (/set-password, see resources/views/mail/forgot-password.blade.php).
export const ROUTES = {
  LOGIN: KERNEL_ROUTES.LOGIN,
  SET_PASSWORD: "auth.set-password",
}

// Guest-only pages on the Empty layout. Module routes inherit nothing from the
// core groups, so the full stack is stated here: ForceTypes + Authentication
// (loads the current user from the stored token) before Guest can decide.
RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/login",
    () => import("@modules/Auth/resources/ts/pages/LoginPage.vue"),
    ROUTES.LOGIN
  )
  RouteDesigner.route(
    "/set-password/:token",
    () => import("@modules/Auth/resources/ts/pages/SetPasswordPage.vue"),
    ROUTES.SET_PASSWORD
  )
})
  .layout("Empty")
  .middleware([ForceTypes, Authentication, Guest])
  .props()
