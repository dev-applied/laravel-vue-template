import { forEach, isFunction, merge, omit, trim } from "lodash"
import type { RouteConfig } from "vue-router/types/router"
import RouteDesigner from "@/router/RouteDesigner"

export default class Route {
  private readonly uri: string

  private readonly page: {
    default: string | { template: string }
    [key: string]: string | { template: string }
  }

  private readonly name?: string

  private attributes: App.Router.RouteAttributes = {
    prefix: undefined,
    middleware: [],
    where: {},
    props: false,
    layout: undefined,
    permissions_all: [],
    permissions_any: []
  }

  private wheres: Record<string, string> = {}

  private routeChildren: Route[] = []

  private parent: Route | null = null

  constructor(uri: string, page: string | { template: string }, name?: string, attributes: Partial<App.Router.RouteAttributes> = {}) {
    this.uri = uri
    this.page = { default: page }
    this.name = name
    this.attributes = merge({}, this.attributes, attributes)
  }

  public getAttributes(key: string | null = null): any {
    if (!key) return this.attributes
    // @ts-ignore
    return this.attributes[key] ?? null
  }

  public setAttributes(attributes: App.Router.RouteAttributes) {
    this.attributes = attributes
  }

  // Parameter Conditions

  public where(name: string | object, expression: string | null = null) {
    forEach(this.parseWhere(name, expression), (expression: string, name: string) => {
      this.wheres[name] = expression
    })

    return this
  }

  public whereUuid(parameters: string[] | string) {
    return this.assignExpressionToParameters(parameters, "[\\da-fA-F]{8}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{12}")
  }

  public whereNumber(parameters: string[] | string) {
    return this.assignExpressionToParameters(parameters, "\\d")
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
    return typeof name === "object" ? name : { [name]: expression }
  }

  // Route Modifiers

  public child(route: Route) {
    RouteDesigner.routes.pop()
    this.routeChildren.push(route.setParent(this))

    return this
  }

  public children(routes: Route[] | (() => void)) {
    if (isFunction(routes)) {
      const startIndex = RouteDesigner.routes.length
      routes()
      routes = RouteDesigner.routes.splice(startIndex, RouteDesigner.routes.length - 1)
    }
    this.routeChildren = this.routeChildren.concat(routes.map((r) => r.setParent(this)))

    return this
  }

  public setParent(route: Route) {
    this.parent = route

    return this
  }

  public layout(layout: App.Layout) {
    this.attributes.layout = layout

    return this
  }

  public namedView(view: string, component: string | { template: string }): Route {
    this.page[view] = component

    return this
  }

  public passProps(): Route {
    this.attributes.props = true

    return this
  }

  public meta(meta: Record<string, any>): Route {
    this.attributes = merge({}, this.attributes, omit(meta, ["prefix", "middleware", "where", "props", "layout", "permissions_all", "permissions_any"]))

    return this
  }

  public addPermissionAny(permissions: string | string[]): Route {
    this.attributes.permissions_any = this.attributes.permissions_any.concat(Array.isArray(permissions) ? permissions : [permissions])

    return this
  }

  public addPermissionAll(permissions: string | string[]): Route {
    this.attributes.permissions_all = this.attributes.permissions_any.concat(Array.isArray(permissions) ? permissions : [permissions])

    return this
  }

  // Internal Functions

  public compile(): RouteConfig {
    const components: {
      default: (() => Promise<any>) | { template: string }
      [key: string]: (() => Promise<any>) | { template: string }
    } = {
      default: typeof this.page.default === "string" ? () => import(`./../pages/${this.page.default}.vue`) : this.page.default
    }

    Object.keys(this.page).forEach((key: string) => {
      if (key === "default") return

      // @ts-ignore
      components[key] = typeof this.page[key] === "string" ? () => import(`./../components/${this.page[key]}.vue`) : this.page[key]
    })

    return {
      name: this.name,
      path: this.compileUri(this.uri),
      components,
      props: { default: this.attributes.props ?? false },
      // @ts-ignore
      meta: omit(this.attributes, ["where", "prefix"]),
      children: this.routeChildren.map((child) => child.compile())
    }
  }

  public compileUri(uri: string): string {
    const PATH_REGEXP = new RegExp(["(\\\\.)", "([\\/.])?(?:(?:\\:(\\w+)(?:\\(((?:\\\\.|[^\\\\()])+)\\))?|\\(((?:\\\\.|[^\\\\()])+)\\))([+*?])?|(\\*))"].join("|"), "g")
    let res

    while ((res = PATH_REGEXP.exec(uri)) != null) {
      const parameter = res[3]
      if (this.wheres[parameter]) {
        uri = uri.replace(parameter, parameter + `(${this.wheres[parameter]})`)
      }
    }

    uri = trim(uri, "/")
    if (!this.isChild()) {
      uri = (this.attributes.prefix ? `/${trim(this.attributes.prefix, "/")}` : "") + (uri ? `/${uri}` : "")
    }
    return uri
  }

  public isChild() {
    return !!this.parent
  }
}
