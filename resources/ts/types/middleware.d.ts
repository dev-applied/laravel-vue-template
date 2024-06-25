declare namespace App.Middleware {
  import type { Route, NavigationGuardNext, RouteLocationNormalizedLoaded, RouteLocationNormalized } from "vue-router"

  export type Options = {
    to: Route
    from: Route
    cancel: NavigationGuardNext
  }

  export type Caller = (
    to: Route,
    from: Route,
    cancel: NavigationGuardNext
  ) => Promise<void>

  export interface Instance {
    handle(to: RouteLocationNormalized, from: RouteLocationNormalizedLoaded, next: MiddlewareCaller, cancel: NavigationGuardNext): Promise<void>
  }

  export interface Constructor {
    new(): Middleware
  }
}
