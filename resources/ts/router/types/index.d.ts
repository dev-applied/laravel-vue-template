declare namespace App.Router {
  import type {RouteMeta} from "vue-router/types/router"

  export type RouteAttributes = RouteMeta & {
    prefix?: string
    middleware?: App.Middleware[],
    where?: Record<string, string>
    props?: boolean,
    layout?: App.Layouts,
    permissions_all?: string[],
    permissions_any?: string[],
    redirect?: string
  }
}
