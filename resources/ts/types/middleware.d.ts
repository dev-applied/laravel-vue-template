declare namespace App.Middleware {
  import type { Route, NavigationGuardNext } from "vue-router/types/router"

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
    handle(to: Route, from: Route, next: MiddlewareCaller, cancel: NavigationGuardNext): Promise<void>
  }

  export interface Constructor {
    new(): Middleware
  }
}
