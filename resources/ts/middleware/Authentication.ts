import { useUserStore } from "@/stores/user"
import type { NavigationGuardNext, RouteRecordNormalized } from "vue-router"

export default class Authentication implements App.Middleware.Instance {
  async handle(
    to: RouteRecordNormalized,
    from: RouteRecordNormalized,
    next: App.Middleware.Caller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    const userStore = useUserStore()
    await userStore.loadUser()

    await next(to, from, cancel)
  }
}
