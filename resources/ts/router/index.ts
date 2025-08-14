import {createRouter, createWebHistory} from "vue-router"
import "@/router/paths"
import RouteDesigner from "@/router/RouteDesigner"
import Pipeline from "@/middleware/Pipeline"

const router = createRouter({
  history: createWebHistory(),
  routes: RouteDesigner.compile(),
  scrollBehavior(to, from, savedPosition) {
    if (to.hash) {
      return {
        el: to.hash,
        top: 130,
      }
    }
    if (savedPosition) {
      return savedPosition
    }
    return {top: 0}
  }
})


const pipeline = new Pipeline()
router.beforeEach(pipeline.handle.bind(pipeline))

export default router
