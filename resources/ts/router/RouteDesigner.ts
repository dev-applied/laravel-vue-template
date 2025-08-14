import {Redirect, Route, RouteGroup} from "@/router/internal"
import type {RouteRecordRaw} from "vue-router"

export default class RouteDesigner {
  public static routes: (Route | RouteGroup | Redirect)[] = []

  private static activeGroup?: RouteGroup

  private static activeRoute?: Route

  private static patterns: Record<string, string> = {}

  private static notFound?: Route

  public static route(uri: string, page: string | { template: string }, name?: string): Route {
    if (this.activeRoute) {
      // Active route takes priority over active group
      return this.activeRoute._route(uri, page, name)
    }
    if (this.activeGroup) {
      return this.activeGroup._route(uri, page, name)
    }

    const route = new Route(uri, page, name)
    this.routes.push(route)

    return route
  }

  public static redirect(from: string, to: string): Redirect {
    if (this.activeRoute) {
      // Active route takes priority over active group
      return this.activeRoute._redirect(from, to)
    }
    if (this.activeGroup) {
      return this.activeGroup._redirect(from, to)
    }
    const redirect = new Redirect(from, to)
    this.routes.push(redirect)

    return redirect
  }

  public static group(uri: string, routes: () => void): RouteGroup {
    if (this.activeRoute) {
      // Active route takes priority over active group
      return this.activeRoute._group(uri, routes)
    }
    if (this.activeGroup) {
      return this.activeGroup._group(uri, routes)
    }
    const group = new RouteGroup(uri, routes)
    this.routes.push(group)

    return group
  }

  public static setNotFound(page: string) {
    this.notFound = new Route("/:catchAll(.*)", page, "not-found")
    return this.notFound
  }

  public static pattern(key: string, pattern: string) {
    this.patterns[key] = pattern
  }

  public static getPatterns() {
    return this.patterns
  }

  public static compile(): RouteRecordRaw[] {
    let routes = this.routes.map((route) => route._compile()).flat()
    if (this.notFound) {
      routes = routes.concat(this.notFound._compile())
    }
    return routes
  }

  // Internal Functions
  public static _setActiveGroup(group: RouteGroup | undefined) {
    this.activeGroup = group
  }

  public static _setActiveRoute(route: Route | undefined) {
    this.activeRoute = route
  }
}
