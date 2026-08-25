// Inline `import("vue-router")` type references rather than a top-level import.
//
// A `.d.ts` containing top-level imports becomes a MODULE, and `declare
// namespace` inside a module is no longer global. The previous version put
// `import type ... from "vue-router"` INSIDE the namespace body, which
// TypeScript parses but does not resolve — so `Route`,
// `RouteLocationNormalized` and friends were all silently unresolved here.
// That is why ForceTypes.ts reported `params` as missing on a route: the type
// it annotated with never actually referred to vue-router's.
//
// It also referenced two names that do not exist — `MiddlewareCaller` (it is
// `Caller`) and `Middleware` (it is `Instance`) — and `Route`, which vue-router
// 4 dropped in favour of `RouteLocationNormalized`.
declare namespace App.Middleware {
  type RouteLocationNormalized = import("vue-router").RouteLocationNormalized
  type RouteLocationNormalizedLoaded = import("vue-router").RouteLocationNormalizedLoaded
  type NavigationGuardNext = import("vue-router").NavigationGuardNext

  export type Options = {
    to: RouteLocationNormalized
    from: RouteLocationNormalizedLoaded
    cancel: NavigationGuardNext
  }

  export type Caller = (
    to: RouteLocationNormalized,
    from: RouteLocationNormalizedLoaded,
    cancel: NavigationGuardNext
  ) => Promise<void>

  export interface Instance {
    handle(
      to: RouteLocationNormalized,
      from: RouteLocationNormalizedLoaded,
      next: Caller,
      cancel: NavigationGuardNext
    ): Promise<void>
  }

  export interface Constructor {
    new(): Instance
  }
}
