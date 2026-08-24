declare namespace App.Router {
  import type {RouteMeta} from "vue-router/types/router"

  /**
   * A route's page: a string resolved against resources/ts/pages/, an inline
   * template object, or a lazy component import (the form modules use, since
   * the string resolver cannot see modules/).
   */
  export type Page = string | { template: string } | (() => Promise<unknown>)

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
