import type { Route, NavigationGuardNext } from 'vue-router/types/router'

export type MiddlewareOptions = {
  to: Route
  from: Route
  cancel: NavigationGuardNext
}

export type MiddlewareCaller = (
  to: Route,
  from: Route,
  cancel: NavigationGuardNext
) => Promise<void>

export interface Middleware {
  handle(to: Route, from: Route, next: MiddlewareCaller, cancel: NavigationGuardNext): Promise<void>
}

export interface MiddlewareConstructor {
  new (): Middleware
}
