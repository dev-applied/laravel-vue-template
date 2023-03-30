import Router from 'vue-router'
import type {VueRouter, Position, Route, RouteConfig} from "vue-router/types/router";
import Authentication from '@/middleware/authentication'
import Authorization from '@/middleware/authorization'
import { $auth } from '@/plugins/auth'
import middleware from "@/middleware/middleware";
import type {RoutePath} from "@/router/paths";
import paths from "@/router/paths";
import Vue from "vue";

let internalRouter: VueRouter | null = null
export function createRouter(): VueRouter {
  if (internalRouter) return internalRouter

  Vue.use(Router)

  function route (path: RoutePath): RouteConfig {
    path.meta = path.meta ? path.meta : {}
    path.meta.middleware = path.meta.middleware ? path.meta.middleware : []
    // @ts-ignore
    path.meta.middleware = [Authentication, Authorization].concat(path.meta.middleware)

    const explainedRoute = {
      ...path,
      component: () => import(`@/pages/${path.page}.vue`),
    }

    if (path.children) {
      const children: any[] = []
      path.children.forEach((child: RoutePath) => {
        children.push(route(child))
      })
      explainedRoute.children = children
    }

    // @ts-ignore
    return explainedRoute
  }

  const routes: RouteConfig[] = paths.map(path => route(path))

  const router = new Router({
    mode: 'history',
    routes: routes,
    scrollBehavior (to: Route, from: Route, savedPosition: Position | void){
      if (savedPosition) {
        return savedPosition
      }
      if (to.hash) {
        return { selector: to.hash }
      }
      return { x: 0, y: 0 }
    },
  })


  const context = {
    router,
    $auth,
  }

// @ts-ignore
  router.beforeEach(middleware(context))

  internalRouter = router

  return internalRouter
}
