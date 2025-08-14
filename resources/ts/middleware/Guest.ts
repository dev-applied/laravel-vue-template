import {$auth} from "@/plugins/auth"
import type {NavigationGuardNext, RouteLocationNormalized, RouteLocationNormalizedLoaded} from "vue-router"
import routeTo from "@/plugins/routeTo.ts"
import {ROUTES} from "@/router/paths"

export default class Guest implements App.Middleware.Instance {
  async handle(
    to: RouteLocationNormalized,
    from: RouteLocationNormalizedLoaded,
    next: App.Middleware.Caller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    if ($auth.user) {
      return cancel(routeTo(ROUTES.DASHBOARD))
    }

    await next(to, from, cancel)
  }
}
