import 'vue-router'
import type { Layout } from '@/types'
import type { MiddlewareConstructor } from '@/middleware/middleware'
import type { Dictionary, RouteMeta, RouteRecord } from 'vue-router/types/router'

export interface Route {
  path: string
  name?: string | null
  hash: string
  query: Dictionary<string | (string | null)[]>
  params: Dictionary<any>
  fullPath: string
  matched: RouteRecord[]
  redirectedFrom?: string
  meta: RouteMeta
}

declare module 'vue-router/types/router' {
  export interface Route {
    path: string
    name?: string | undefined
    hash: string
    query: Dictionary<string | (string | null)[]>
    params: Dictionary<any>
    fullPath: string
    matched: RouteRecord[]
    redirectedFrom?: string
    meta: RouteMeta
  }

  export interface RouteMeta {
    layout?: Layout
    middleware: MiddlewareConstructor[]
    permissions_all: string[]
    permissions_any: string[]
    title?: string
  }
}
