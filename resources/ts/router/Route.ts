import merge from "lodash.merge"
import RouteDesigner from "@/router/RouteDesigner"
import {RouteBuilder, RouteGroup} from "@/router/internal"
import type {RouteRecordRaw} from "vue-router"

/** Strips leading/trailing slashes only. Replaces lodash.trim
 * (unpatched ReDoS CVE-2020-28500). */
const trimSlashes = (s: string) => s.replace(/^\/+|\/+$/g, '')

export class Route extends RouteBuilder {
  // Build-time glob of every app page and component a string route name can
  // resolve to. Keys look like "../pages/items/ItemListPage.vue".
  private static readonly pageModules: Record<string, () => Promise<any>> = {
    ...import.meta.glob("../pages/**/*.vue"),
    ...import.meta.glob("../components/**/*.vue"),
  }

  private readonly uri: string

  private readonly page: {
    default: App.Router.Page
    [key: string]: App.Router.Page
  }

  private readonly name?: string

  constructor(uri: string, page: App.Router.Page, name?: string, attributes: Partial<App.Router.RouteAttributes> = {}) {
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
  public _route(uri: string, page: App.Router.Page, name?: string) {
    const route = super._route(uri, page, name)
    route._setParent(this)

    return route
  }

  public _group(uri: string, routes: () => void): RouteGroup {
    const attributes = this._getAttributes()
    attributes.prefix = attributes.prefix + '/' + trimSlashes(this.uri)
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
    // String pages resolve against a glob of resources/ts/pages/. A glob map
    // (not a variable dynamic import) because Vite's import-vars transform
    // only supports one path level — `import(`../pages/${x}.vue`)` throws
    // "Unknown variable dynamic import" for nested pages like
    // items/ItemListPage. Module pages can't reach this glob at all, so
    // modules pass a lazy import instead:
    //   RouteDesigner.route('/x', () => import('@modules/X/resources/ts/pages/XPage.vue'), NAME)
    // A function page is used as the component loader directly (and
    // code-splits per module for free).
    const resolvePage = (kind: "pages" | "components", name: string): (() => Promise<any>) => {
      const loader = Route.pageModules[`../${kind}/${name}.vue`]
      if (!loader) {
        throw new Error(`Route "${this.uri}": no ${kind} file matches "${name}" (expected resources/ts/${kind}/${name}.vue)`)
      }
      return loader
    }

    const components: {
      default: (() => Promise<any>) | { template: string }
      [key: string]: (() => Promise<any>) | { template: string }
    } = {
      default: typeof this.page.default === "string" ? resolvePage("pages", this.page.default) : this.page.default
    }

    Object.keys(this.page).forEach((key: string) => {
      if (key === "default") return

      // @ts-ignore
      components[key] = typeof this.page[key] === "string" ? resolvePage("components", this.page[key]) : this.page[key]
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
