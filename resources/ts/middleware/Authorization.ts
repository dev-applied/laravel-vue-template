import {$auth} from "@/plugins/auth"
import type {NavigationGuardNext, RouteLocationNormalized, RouteLocationNormalizedLoaded} from "vue-router"
import {ROUTES} from "@/router/paths.ts"
import routeTo from "@/plugins/routeTo.ts"

export default class Authorization implements App.Middleware.Instance {
  async handle(
    to: RouteLocationNormalized,
    from: RouteLocationNormalizedLoaded,
    next: App.Middleware.Caller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    if (!$auth.user) {
      // Send guests to login with a deep link back — LoginPage already
      // restores `?to=` after auth (same pattern as the axios 401 handler).
      // Cancelling to "/" (the old behaviour) landed on the 404 page: no "/"
      // route exists.
      return cancel(routeTo(ROUTES.LOGIN, {}, {to: (to as RouteLocationNormalizedLoaded).fullPath}))
    }

    if (to.meta.permissions_all.length && !$auth.hasAllPermissions(to.meta.permissions_all)) {
      return cancel(routeTo(ROUTES.DASHBOARD, {}, {error: 'You do not have permission to access this page.'}))
    }

    if (to.meta.permissions_any.length && !$auth.hasAnyPermissions(to.meta.permissions_any)) {
      return cancel(routeTo(ROUTES.DASHBOARD, {}, {error: 'You do not have permission to access this page.'}))
    }

    // `roles` (plural), not `role` — AuthUser has no singular `role` field, so
    // this compared every route's role list against `undefined` and failed for
    // everyone. `some` rather than `includes` because a user can hold several
    // roles and the route lists the ones that grant access.
    //
    // Like all_permissions, `roles` is contributed by the RolesPermissions
    // module; without it the list is empty and a role-gated route denies, which
    // is the same fail-closed behaviour the permission checks above have.
    const userRoles = $auth.user?.roles ?? []

    if (to.meta.roles.length && !userRoles.some((role) => to.meta.roles.includes(role.name))) {
      return cancel(routeTo(ROUTES.DASHBOARD, {}, {error: 'You do not have permission to access this page.'}))
    }

    await next(to, from, cancel)
  }
}
