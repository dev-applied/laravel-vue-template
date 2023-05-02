import { useUserStore } from "@/stores/user"
import type { Middleware, MiddlewareCaller } from "@/types"
import type { NavigationGuardNext, Route } from "vue-router/types/router"

export default class Authentication implements Middleware {
  async handle(
    to: Route,
    from: Route,
    next: MiddlewareCaller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    const userStore = useUserStore()
    await userStore.loadUser()

    await next(to, from, cancel)
  }
}
