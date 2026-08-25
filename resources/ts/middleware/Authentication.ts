import { useUserStore } from "@/stores/user"
import type { NavigationGuardNext, RouteLocationNormalized, RouteLocationNormalizedLoaded } from "vue-router"

export default class Authentication implements App.Middleware.Instance {
  async handle(
    // RouteRecordNormalized is a route DEFINITION; the pipeline passes a
    // navigation target. They are different shapes, and the mismatch was
    // only invisible because the vue-router shim had broken the types.
    to: RouteLocationNormalized,
    from: RouteLocationNormalizedLoaded,
    next: App.Middleware.Caller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    const userStore = useUserStore()
    await userStore.loadUser()

    await next(to, from, cancel)
  }
}
