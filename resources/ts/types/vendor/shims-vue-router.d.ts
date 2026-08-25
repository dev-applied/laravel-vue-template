// Augments vue-router's RouteMeta with the fields RouteBuilder writes and the
// middleware pipeline reads.
//
// `RouteMeta` is vue-router's documented extension point and is declared as an
// INTERFACE, so this merges. The previous version of this file also redeclared
// `RouteLocationNormalized` with two members — but vue-router declares that as
// a TYPE ALIAS, which an interface cannot merge with. The declaration shadowed
// the real one instead, so every route object in the app typed as
// `RouteLocationNormalized` lost `params`, `query`, `name` and the rest. That
// is the actual source of "Property 'params' does not exist" in ForceTypes.
//
// Its two imports (`@/types`, `@/middleware/middleware`) also pointed at
// modules that do not exist, leaving `Layout` and `MiddlewareConstructor`
// unresolved — so `meta.middleware` was silently `any[]`. Both now reference
// the global namespaces that really declare them.
declare module "vue-router" {
  export interface RouteMeta {
    layout?: App.Layout
    middleware: App.Middleware.Constructor[]
    permissions_all: string[]
    permissions_any: string[]
    roles: string[]
    title?: string
  }
}

export {}
