import Route from "@/router/Route"
import { cloneDeep, forEach, merge, trim, union } from "lodash"

export default class RouteDesigner {
  private static routes: Route[] = []

  private static groupStack: Partial<App.Router.RouteAttributes>[] = []

  private static patterns: Record<string, string> = {}

  private static notFound?: Route

  public static route(
    uri: string,
    page: string | { template: string },
    name: string,
    attributes: Partial<App.Router.RouteAttributes> = {}
  ) {
    const route = this.createRoute(uri, page, name, attributes)
    this.routes.push(route)

    return route
  }

  public static group(attributes: Partial<App.Router.RouteAttributes>, routes: () => void) {
    this.updateGroupStack(attributes)
    routes()
    this.groupStack.pop()

    return this
  }

  public static setNotFound(page: string, attributes: Partial<App.Router.RouteAttributes> = {}) {
    this.notFound = this.newRoute("*", page, "NotFound", attributes)
  }

  public static pattern(key: string, pattern: string) {
    this.patterns[key] = pattern
  }

  public static compile() {
    const routes = this.routes.map((route) => route.compile())
    if (this.notFound) {
      routes.push(this.notFound.compile())
    }
    return routes
  }

  private static updateGroupStack(attributes: Partial<App.Router.RouteAttributes>) {
    if (this.hasGroupStack()) {
      attributes = this.mergeWithLastGroup(attributes)
    }

    this.groupStack.push(attributes)
  }

  private static hasGroupStack(): boolean {
    return this.groupStack.length > 0
  }

  public static mergeWithLastGroup(attributes: Partial<App.Router.RouteAttributes>): App.Router.RouteAttributes {
    const oldAttributes = cloneDeep(this.groupStack[this.groupStack.length - 1])

    forEach(attributes, (value, key) => {
      if (key === "prefix") {
        return oldAttributes.prefix = this.formatPrefix(attributes, oldAttributes)
      }
      if (Array.isArray(value)) {
        oldAttributes[key] = union([], oldAttributes[key] ?? [], value)
      } else if (typeof value === "object") {
        oldAttributes[key] = merge({}, oldAttributes[key] ?? {}, value)
      } else if (typeof value !== "undefined") {
        oldAttributes[key] = value
      }
    })
  }

  private static formatPrefix(
    newAttributes: Partial<App.Router.RouteAttributes>,
    oldAttributes: Partial<App.Router.RouteAttributes>
  ) {
    const oldPrefix = oldAttributes.prefix ?? ""

    return newAttributes.prefix
      ? trim(oldPrefix, "/") + "/" + trim(newAttributes.prefix, "/")
      : oldPrefix
  }

  private static createRoute(
    uri: string,
    page: string | { template: string },
    name: string,
    attributes: Partial<App.Router.RouteAttributes> = {}
  ) {
    const route = this.newRoute(uri, page, name, attributes)
    if (this.hasGroupStack()) {
      this.mergeGroupAttributesIntoRoute(route)
    }

    this.addWhereClausesToRoute(route)

    return route
  }

  private static mergeGroupAttributesIntoRoute(route: Route) {
    route.setAttributes(this.mergeWithLastGroup(route.getAttributes()))
  }

  private static addWhereClausesToRoute(route: Route) {
    route.where(merge({}, this.patterns, route.getAttributes().where ?? {}))

    return route
  }

  private static newRoute(
    uri: string,
    page: string | { template: string },
    name: string,
    attributes: Partial<App.Router.RouteAttributes> = {}
  ) {
    return new Route(uri, page, name, attributes)
  }
}
