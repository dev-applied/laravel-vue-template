import { $auth } from '@/plugins/auth'
import type { Middleware, MiddlewareCaller } from '@/types'
import type { NavigationGuardNext } from 'vue-router/types/router'
import type { Route } from '@/types/vendor/shims-vue-router'

export default class Authorization implements Middleware {
  async handle(
    to: Route,
    from: Route,
    next: MiddlewareCaller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    if (!$auth.user) {
      return cancel('/')
    }

    if (to.meta.permissions_all.length && !$auth.hasAllPermissions(to.meta.permissions_all)) {
      return cancel('/dashboard')
    }

    if (to.meta.permissions_any.length && !$auth.hasAnyPermissions(to.meta.permissions_any)) {
      return cancel('/dashboard')
    }

    await next(to, from, cancel)
  }
}
