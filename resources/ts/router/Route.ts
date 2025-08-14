import merge from "lodash.merge"
import trim from "lodash.trim"
import RouteDesigner from "@/router/RouteDesigner"
import {RouteBuilder, RouteGroup} from "@/router/internal"
import type {RouteRecordRaw} from "vue-router"

export class Route extends RouteBuilder {
  private readonly uri: string

  private readonly page: {
    default: string | { template: string }
    [key: string]: string | { template: string }
  }

  private readonly name?: string

  constructor(uri: string, page: string | {
    template: string
  }, name?: string, attributes: Partial<App.Router.RouteAttributes> = {}) {
    super()
    this.uri = uri
    this.page = {default: page}
    this.name = name
    this.attributes = merge({}, this.attributes, attributes)
  }

  // Route Modifiers
  public children(routes: () => void) {
    RouteDesigner._setActiveRoute(this)
    routes()
    RouteDesigner._setActiveRoute(undefined)
    return this
  }

  public redirect(to: string) {
    this.attributes.redirect = to
    return this
  }

  public namedView(view: string, component: string | { template: string }): Route {
    this.page[view] = component

    return this
  }

  /**
   * @internal
   * @param uri
   * @param page
   * @param name
   */
  public _route(uri: string, page: string | { template: string }, name?: string) {
    const route = super._route(uri, page, name)
    route._setParent(this)

    return route
  }

  public _group(uri: string, routes: () => void): RouteGroup {
    const attributes = this._getAttributes()
    attributes.prefix = attributes.prefix + '/' + trim(this.uri, '/')
    const group = new RouteGroup(uri, routes)
    group._setAttributes(this.mergeAttributes({prefix: group._getAttributes().prefix}, attributes))
    this.routes.push(group)
    return group
  }

  /**
   * @internal
   */
  public _compile(): RouteRecordRaw[] {
    this.where(merge({}, RouteDesigner.getPatterns(), this.attributes.where ?? {}))
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

    return [{
      name: this.name,
      path: this.compileUri(this.uri),
      components,
      props: {default: this.attributes.props ?? false},
      redirect: this.attributes.redirect,
      meta: this._getMeta(),
      children: this.routes.map((child) => child._compile()).flat()
    }]
  }
}
