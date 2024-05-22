import { $auth } from "@/plugins/auth"
import type { NavigationGuardNext } from "vue-router/types/router"
import type { Route } from "@/types/vendor/shims-vue-router"

export default class Authorization implements App.Middleware.Instance {
  async handle(
    to: Route,
    from: Route,
    next: App.Middleware.Caller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    if (!$auth.user) {
      return cancel("/")
    }

    if (to.meta.permissions_all.length && !$auth.hasAllPermissions(to.meta.permissions_all)) {
      return cancel("/dashboard")
    }

    if (to.meta.permissions_any.length && !$auth.hasAnyPermissions(to.meta.permissions_any)) {
      return cancel("/dashboard")
    }

    await next(to, from, cancel)
  }
}
