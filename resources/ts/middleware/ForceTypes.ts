import forEach from "lodash.foreach"
import type {NavigationGuardNext, RouteLocationNormalized, RouteLocationNormalizedLoaded} from "vue-router"

export default class ForceTypes implements App.Middleware.Instance {
  async handle(
    to: RouteLocationNormalized,
    from: RouteLocationNormalizedLoaded,
    next: App.Middleware.Caller,
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
      if (param === "true" || param === "false") {
        return (to.params[key] = Boolean(param))
      }
    })

    await next(to, from, cancel)
  }
}
