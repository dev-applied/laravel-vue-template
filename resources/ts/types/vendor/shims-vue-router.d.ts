declare module "vue-router" {
  import type {Layout} from "@/types"
  import type {MiddlewareConstructor} from "@/middleware/middleware"

  export interface RouteMeta {
    layout?: Layout
    middleware: MiddlewareConstructor[]
    permissions_all: string[]
    permissions_any: string[]
    roles: string[]
    title?: string
  }

  export interface RouteLocationNormalized {
    redirectedFrom?: string
    meta: RouteMeta
  }
}

export {}
