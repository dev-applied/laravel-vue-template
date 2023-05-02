import Route from '@/router/Route'
import { last, merge, trim, union } from 'lodash'
import type { RouteMeta } from 'vue-router'

export type RouteAttributes = RouteMeta & {
  prefix?: string
  where?: Record<string, string>
}

export default class RouteDesigner {
  private static routes: Route[] = []

  private static groupStack: Partial<RouteAttributes>[] = []

  private static childStack: Route[] = []

  private static patterns: Record<string, string> = {}

  private static notFound?: Route

  public static route(
    uri: string,
    page: string | { template: string },
    name: string,
    attributes: Partial<RouteAttributes> = {}
  ) {
    const route = this.createRoute(uri, page, name, attributes)
    if (this.hasChildStack()) {
      last(this.childStack)?.child(route)
    } else {
      this.routes.push(route)
    }

    return route
  }

  public static group(attributes: Partial<RouteAttributes>, routes: () => void) {
    this.updateGroupStack(attributes)
    routes()
    this.groupStack.pop()

    return this
  }

  public static children(route: Route, routes: () => void) {
    this.updateChildStack(route)
    routes()
    this.childStack.pop()

    return this
  }

  public static setNotFound(page: string, attributes: Partial<RouteAttributes> = {}) {
    this.notFound = this.newRoute('*', page, 'NotFound', attributes)
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

  private static updateGroupStack(attributes: Partial<RouteAttributes>) {
    if (this.hasGroupStack()) {
      attributes = this.mergeWithLastGroup(attributes)
    }

    this.groupStack.push(attributes)
  }

  private static updateChildStack(route: Route) {
    this.childStack.push(route)
  }

  private static hasGroupStack(): boolean {
    return this.groupStack.length > 0
  }

  private static hasChildStack(): boolean {
    return this.childStack.length > 0
  }

  public static mergeWithLastGroup(
    attributes: Partial<RouteAttributes>,
    prependExistingPrefix: boolean = true
  ): RouteAttributes {
    const oldAttributes = this.groupStack[this.groupStack.length - 1]
    return {
      prefix: this.formatPrefix(attributes, oldAttributes, prependExistingPrefix),
      where: this.formatWhere(attributes, oldAttributes),
      middleware: merge(oldAttributes.middleware ?? [], attributes.middleware ?? []),
      layout: attributes.layout ?? oldAttributes.layout ?? undefined,
      permissions_all: union(attributes.permissions_all ?? [], oldAttributes.permissions_all ?? []),
      permissions_any: union(attributes.permissions_any ?? [], oldAttributes.permissions_any ?? []),
      title: attributes.title ?? oldAttributes.title ?? undefined
    }
  }

  private static formatPrefix(
    newAttributes: Partial<RouteAttributes>,
    oldAttributes: Partial<RouteAttributes>,
    prependExistingPrefix: boolean = true
  ) {
    const oldPrefix = oldAttributes.prefix ?? ''

    if (prependExistingPrefix) {
      return newAttributes.prefix
        ? trim(oldPrefix, '/') + '/' + trim(newAttributes.prefix, '/')
        : oldPrefix
    } else {
      return newAttributes.prefix
        ? trim(newAttributes.prefix, '/') + '/' + trim(oldPrefix, '/')
        : oldPrefix
    }
  }

  private static formatWhere(
    newAttributes: Partial<RouteAttributes>,
    oldAttributes: Partial<RouteAttributes>
  ) {
    return merge(oldAttributes.where ?? {}, newAttributes.where ?? {})
  }

  private static prefix(uri: string) {
    if (this.hasChildStack()) {
      return uri
    }
    const prefix = '/' + trim(trim(this.getLastGroupPrefix(), '/') + '/' + trim(uri, '/'), '/')
    return prefix ? prefix : '/'
  }

  private static getLastGroupPrefix() {
    if (this.hasGroupStack()) {
      const last = this.groupStack[this.groupStack.length - 1]

      return last.prefix ?? ''
    }

    return ''
  }

  private static createRoute(
    uri: string,
    page: string | { template: string },
    name: string,
    attributes: Partial<RouteAttributes> = {}
  ) {
    const route = this.newRoute(this.prefix(uri), page, name, attributes)
    if (this.hasGroupStack()) {
      this.mergeGroupAttributesIntoRoute(route)
    }

    this.addWhereClausesToRoute(route)

    return route
  }

  private static mergeGroupAttributesIntoRoute(route: Route) {
    route.setAttributes(this.mergeWithLastGroup(route.getAttributes(), false))
  }

  private static addWhereClausesToRoute(route: Route) {
    route.where(merge(this.patterns, route.getAttributes().where ?? []))

    return route
  }

  private static newRoute(
    uri: string,
    page: string | { template: string },
    name: string,
    attributes: Partial<RouteAttributes> = {}
  ) {
    return new Route(uri, page, name, attributes, this.hasChildStack())
  }
}
