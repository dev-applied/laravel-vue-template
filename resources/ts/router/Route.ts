import type { RouteAttributes } from '@/router/RouteDesigner'
import { forEach, merge, omit, trim } from 'lodash'
import type { RouteConfig } from 'vue-router/types/router'
import type { Layout } from '@/types'

export default class Route {
  private readonly uri: string

  private readonly page: {
    default: string | { template: string }
    [key: string]: string | { template: string }
  }

  private name: string

  private attributes: RouteAttributes = {
    middleware: [],
    permissions_any: [],
    permissions_all: []
  }

  private wheres: Record<string, string> = {}

  private children: Route[] = []

  private readonly isChild: boolean = false

  constructor(
    uri: string,
    page: string | { template: string },
    name: string,
    attributes: Partial<RouteAttributes> = {},
    isChild: boolean = false
  ) {
    this.uri = uri
    this.page = { default: page }
    this.name = name
    this.attributes = merge({}, this.attributes, attributes)
    this.isChild = isChild
  }

  public getAttributes(key: string | null = null): any {
    if (!key) return this.attributes
    // @ts-ignore
    return this.attributes[key] ?? null
  }

  public setAttributes(attributes: RouteAttributes) {
    this.attributes = attributes
  }

  public where(name: string | object, expression: string | null = null) {
    forEach(this.parseWhere(name, expression), (expression: string, name: string) => {
      this.wheres[name] = expression
    })

    return this
  }

  public child(route: Route) {
    this.children.push(route)
  }

  public layout(layout: Layout) {
    this.attributes.layout = layout
  }

  public compile(): RouteConfig {
    const components: {
      default: (() => Promise<any>) | { template: string }
      [key: string]: (() => Promise<any>) | { template: string }
    } = {
      default:
        typeof this.page.default === 'string'
          ? () => import(`./../pages/${this.page.default}.vue`)
          : this.page.default
    }

    Object.keys(this.page).forEach((key: string) => {
      if (key === 'default') return

      // @ts-ignore
      components[key] =
        typeof this.page[key] === 'string'
          ? () => import(`./../components/${this.page[key]}.vue`)
          : this.page[key]
    })

    return {
      path: this.compileUri(this.uri),
      components,
      // @ts-ignore
      meta: omit(this.attributes, ['where', 'prefix']),
      children: this.children.map((child) => child.compile())
    }
  }

  public whereUuid(parameters: string[] | string) {
    return this.assignExpressionToParameters(
      parameters,
      '[\\da-fA-F]{8}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{12}'
    )
  }

  public whereNumber(parameters: string[] | string) {
    return this.assignExpressionToParameters(parameters, '\\d')
  }

  public compileUri(uri: string): string {
    const PATH_REGEXP = new RegExp(
      [
        '(\\\\.)',
        '([\\/.])?(?:(?:\\:(\\w+)(?:\\(((?:\\\\.|[^\\\\()])+)\\))?|\\(((?:\\\\.|[^\\\\()])+)\\))([+*?])?|(\\*))'
      ].join('|'),
      'g'
    )
    let res

    while ((res = PATH_REGEXP.exec(uri)) != null) {
      const parameter = res[3]
      if (this.wheres[parameter]) {
        uri = uri.replace(parameter, parameter + `(${this.wheres[parameter]})`)
      }
    }

    uri = trim(uri, '/')
    if (!this.isChild) {
      uri = '/' + uri
    }
    return uri
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
    return typeof name === 'object' ? name : { [name]: expression }
  }

  public namedView(view: string, component: string | { template: string }): Route {
    this.page[view] = component

    return this
  }
}
