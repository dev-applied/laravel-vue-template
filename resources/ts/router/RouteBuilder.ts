import cloneDeep from "lodash.clonedeep"
import forEach from "lodash.foreach"
import merge from "lodash.merge"
import omit from "lodash.omit"
import trim from "lodash.trim"
import union from "lodash.union"
import type {RouteMeta, RouteRecordRaw} from "vue-router"
import {Redirect, Route, RouteGroup} from "@/router/internal"

export abstract class RouteBuilder {
  protected attributes: Required<App.Router.RouteAttributes> = {
    prefix: undefined,
    middleware: [],
    where: {},
    props: false,
    layout: undefined,
    permissions_all: [],
    permissions_any: [],
    roles: [],
    redirect: undefined
  }

  protected routes: RouteBuilder[] = []

  protected parent: Route | null = null

  public where(name: string | object, expression: string | null = null) {
    forEach(this.parseWhere(name, expression), (expression: string, name: string) => {
      this.attributes.where[name] = expression
    })

    return this
  }

  public whereUuid(parameters: string[] | string) {
    return this.assignExpressionToParameters(parameters, "[\\da-fA-F]{8}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{12}")
  }

  public whereNumber(parameters: string[] | string) {
    return this.assignExpressionToParameters(parameters, "\\d")
  }

  public layout(layout: App.Layout) {
    this.attributes.layout = layout

    return this
  }

  public props(): this {
    this.attributes.props = true

    return this
  }

  public meta(meta: Record<string, any>): this {
    this.attributes = merge({}, this.attributes, omit(meta, ["prefix", "middleware", "where", "props", "layout", "permissions_all", "permissions_any"]))

    return this
  }

  public addPermissionAny(permissions: string | string[]): this {
    this.attributes.permissions_any = this.attributes.permissions_any.concat(Array.isArray(permissions) ? permissions : [permissions])

    return this
  }

  public addPermissionAll(permissions: string | string[]): this {
    this.attributes.permissions_all = this.attributes.permissions_any.concat(Array.isArray(permissions) ? permissions : [permissions])

    return this
  }

  public middleware(middleware: App.Middleware.Constructor | App.Middleware.Constructor[]): this {
    this.attributes.middleware = this.attributes.middleware.concat(Array.isArray(middleware) ? middleware : [middleware])

    return this
  }

  public roles(roles: string | string[]): this {
    this.attributes.roles = Array.isArray(roles) ? roles : [roles]

    return this
  }

  /**
   * @internal
   */
  public _getAttributes() {
    return cloneDeep(this.attributes)
  }

  /**
   * @internal
   * @param attributes
   */
  public _setAttributes(attributes: App.Router.RouteAttributes) {
    this.attributes = attributes
  }

  /**
   * @internal
   * @param route
   */
  public _setParent(route: Route) {
    this.parent = route

    return this
  }

  /**
   * @internal
   */
  public abstract _compile(): RouteRecordRaw[]

  /**
   * @internal
   */
  public _getMeta(): RouteMeta {
    // @ts-ignore
    return omit(this.attributes, ["where", "prefix", "props", "redirect"])
  }

  /**
   * @internal
   * @param uri
   * @param page
   * @param name
   */
  public _route(uri: string, page: string | { template: string }, name?: string): Route {
    const route = new Route(uri, page, name)
    route._setAttributes(this._getAttributes())
    this.routes.push(route)

    return route
  }

  /**
   * @internal
   * @param uri
   * @param routes
   */
  public _group(uri: string, routes: () => void): RouteGroup {
    const group = new RouteGroup(uri, routes)
    group._setAttributes(this.mergeAttributes({prefix: group.attributes.prefix}, this.attributes))
    this.routes.push(group)
    return group
  }

  /**
   * @internal
   * @param to
   * @param from
   */
  public _redirect(to: string, from: string): Redirect {
    const redirect = new Redirect(to, from)
    this.routes.push(redirect)
    return redirect
  }

  protected compileUri(uri: string): string {
    const PATH_REGEXP = new RegExp(["(\\\\.)", "([\\/.])?(?:(?:\\:(\\w+)(?:\\(((?:\\\\.|[^\\\\()])+)\\))?|\\(((?:\\\\.|[^\\\\()])+)\\))([+*?])?|(\\*))"].join("|"), "g")
    let res: string[] | null

    while ((res = PATH_REGEXP.exec(uri)) != null) {
      const parameter = res[3]
      if (this.attributes.where?.[parameter]) {
        uri = uri.replace(parameter, parameter + `(${this.attributes.where[parameter]})`)
      }
    }

    uri = trim(uri, "/")
    if (!this._isChild()) {
      uri = (this.attributes.prefix ? `/${trim(this.attributes.prefix, "/")}` : "") + (uri ? `/${uri}` : "")
    }
    return uri
  }

  protected _isChild() {
    return !!this.parent
  }

  protected mergeAttributes(attributes: Partial<App.Router.RouteAttributes>, oldAttributes: Partial<App.Router.RouteAttributes>): App.Router.RouteAttributes {
    oldAttributes = cloneDeep(oldAttributes)
    forEach(attributes, (value, key) => {
      if (key === "prefix") {
        oldAttributes.prefix = this.formatPrefix(attributes, oldAttributes)
        return
      }
      if (Array.isArray(value)) {
        oldAttributes[key] = union([], oldAttributes[key] ?? [], value)
      } else if (typeof value === "object") {
        oldAttributes[key] = merge({}, oldAttributes[key] ?? {}, value)
      } else if (typeof value !== "undefined") {
        oldAttributes[key] = value
      }
    })

    return oldAttributes
  }

  protected formatPrefix(newAttributes: Partial<App.Router.RouteAttributes>, oldAttributes: Partial<App.Router.RouteAttributes>) {
    const oldPrefix = oldAttributes.prefix ?? ""

    return newAttributes.prefix ? trim(oldPrefix, "/") + "/" + trim(newAttributes.prefix, "/") : oldPrefix
  }

  private assignExpressionToParameters(parameters: string[] | string, expression: string) {
    parameters = Array.isArray(parameters) ? parameters : [parameters]
    const compiledParameters: Record<string, string> = {}

    parameters.forEach((parameter) => {
      compiledParameters[parameter] = expression
    })

    return this.where(parameters)
  }

  private parseWhere(name: string | object, expression: string | null = null) {
    return typeof name === "object" ? name : {[name]: expression}
  }
}
