import moment from "moment"
import { forEach } from "lodash"
import type { NavigationGuardNext } from "vue-router/types/router"
import type { Route } from "@/types/vendor/shims-vue-router"

export default class ForceTypes implements App.Middleware.Middleware {
  async handle(
    to: Route,
    from: Route,
    next: App.Middleware.MiddlewareCaller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    forEach(to.params, (param: string, key: string) => {
      if (param === null || param === undefined) {
        return (to.params[key] = undefined)
      }
      // @ts-ignore
      if (!isNaN(param)) {
        return (to.params[key] = Number(param))
      }
      if (moment(param).isValid()) {
        return (to.params[key] = moment(param))
      }
      if (param === "true" || param === "false") {
        return (to.params[key] = Boolean(param))
      }
    })

    await next(to, from, cancel)
  }
}
