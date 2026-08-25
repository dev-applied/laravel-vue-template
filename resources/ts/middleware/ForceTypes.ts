import type {NavigationGuardNext, RouteLocationNormalized, RouteLocationNormalizedLoaded} from "vue-router"

/**
 * Coerces route params to their natural JS types so a page taking `props: true`
 * receives `12` rather than `"12"`.
 */
export default class ForceTypes implements App.Middleware.Instance {
  async handle(
    to: RouteLocationNormalized,
    from: RouteLocationNormalizedLoaded,
    next: App.Middleware.Caller,
    cancel: NavigationGuardNext
  ): Promise<void> {
    // vue-router types params as Record<string, string | string[]>. Coercing
    // them writes values that type does not admit, which is the entire point of
    // this middleware, so the widening is stated once here rather than hidden
    // behind a @ts-ignore on each assignment.
    const params = to.params as Record<string, unknown>

    for (const key of Object.keys(params)) {
      const param = params[key]

      if (param === null || param === undefined) {
        params[key] = undefined
        continue
      }

      // Arrays (repeatable params) are left alone — coercing one element of a
      // list to a number and not the rest is worse than leaving it as strings.
      if (typeof param !== 'string') {
        continue
      }

      if (param === 'true' || param === 'false') {
        // NOT Boolean(param): Boolean("false") is true, so the previous version
        // turned every `?flag=false` route param into `true`.
        params[key] = param === 'true'
        continue
      }

      // Guard the empty string explicitly — Number("") is 0, so a blank param
      // would otherwise arrive at the page as a zero it never asked for.
      if (param !== '' && !Number.isNaN(Number(param))) {
        params[key] = Number(param)
      }
    }

    await next(to, from, cancel)
  }
}
