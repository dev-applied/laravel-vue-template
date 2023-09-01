import Router from "vue-router"
import type { Position, Route } from "vue-router/types/router"
import "@/router/paths"
import Vue from "vue"
import Meta from "vue-meta"
import RouteDesigner from "@/router/RouteDesigner"
import Pipeline from "@/middleware/Pipeline"

Vue.use(Router)

const router = new Router({
  mode: "history",
  routes: RouteDesigner.compile(),
  scrollBehavior(to: Route, from: Route, savedPosition: Position | void) {
    if (savedPosition) {
      return savedPosition
    }
    if (to.hash) {
      return { selector: to.hash }
    }
    return { x: 0, y: 0 }
  }
})

Vue.use(Meta)

const pipeline = new Pipeline()
router.beforeEach(pipeline.handle.bind(pipeline))

export default router
