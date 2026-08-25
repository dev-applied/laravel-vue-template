import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"
import Guest from "@/middleware/Guest.ts"

export const ROUTES = {
  INVITATIONS:   "invitations.index",
  ACCEPT_INVITE: "invitations.accept",
}

// The accept page is for people who do not have an account yet, so it sits
// behind Guest, not Authentication — the same shape the Auth module's
// set-password page uses.
RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/accept-invite",
    () => import("@modules/Invitations/resources/ts/pages/AcceptInvitePage.vue"),
    ROUTES.ACCEPT_INVITE
  )
})
  .layout("Empty")
  .middleware([ForceTypes, Guest])
  .props()

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/invitations",
    () => import("@modules/Invitations/resources/ts/pages/InvitationsPage.vue"),
    ROUTES.INVITATIONS
  )
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()
