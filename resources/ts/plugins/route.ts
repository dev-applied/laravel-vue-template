import type { LocationQueryRaw, RouteLocationRaw } from "vue-router"
import router from "@/router"

export default function route(name: string, params: {[p: string]: string | string[] | number | number[] } = {}, query: LocationQueryRaw = {}): RouteLocationRaw {
  const routes = router.options!.routes!.map((route) => [route, ...route.children || []]).flat()
  const route = routes.find((route) => route.name === name)
  if (!route) throw new Error(`Route ${name} not found`)
  return { name, params, query }
}
