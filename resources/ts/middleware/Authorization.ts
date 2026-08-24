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

    if (to.meta.roles.length && !to.meta.roles.includes($auth.user?.role)) {
      return cancel(routeTo(ROUTES.DASHBOARD, {}, {error: 'You do not have permission to access this page.'}))
    }

    await next(to, from, cancel)
  }
}
