import moment from "moment"
import { map } from "lodash"
import type { Middleware, MiddlewareCaller } from "@/types"
import type { NavigationGuardNext } from "vue-router/types/router"
import type { Route } from "@/types/vendor/shims-vue-router"

export default class ForceTypes implements Middleware {
  async handle(
    to: Route,
    from: Route,
    next: MiddlewareCaller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    to.params = map(to.params, (param: string) => {
      if (Number.isInteger(param)) {
        return Number(param)
      }
      if (moment(param).isValid()) {
        return moment(param)
      }
      if (param === "true" || param === "false") {
        return Boolean(param)
      }

      return param
    })

    await next(to, from, cancel)
  }
}
