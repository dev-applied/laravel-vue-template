import { useUserStore } from "@/stores/user"
import type { NavigationGuardNext, Route } from "vue-router/types/router"

export default class Authentication implements App.Middleware.Instance {
  async handle(
    to: Route,
    from: Route,
    next: App.Middleware.Caller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    const userStore = useUserStore()
    await userStore.loadUser()

    await next(to, from, cancel)
  }
}
