import type { LocationQueryRaw, RouteLocationRaw, RouteParamsGeneric } from "vue-router"
import {useRouter} from "vue-router"

export default function route(name: string, params: RouteParamsGeneric = {}, query: LocationQueryRaw = {}): RouteLocationRaw {
  const routes = useRouter().options!.routes!.map((route) => [route, ...route.children || []]).flat()
  const route = routes.find((route) => route.name === name)
  if (!route) throw new Error(`Route ${name} not found`)
  return { name, params, query }
}
