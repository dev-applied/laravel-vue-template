import { createWebHistory, createRouter } from "vue-router"
import "@/router/paths"
import RouteDesigner from "@/router/RouteDesigner"
import Pipeline from "@/middleware/Pipeline"

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: RouteDesigner.compile(),
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }
    if (to.hash) {
      return { selector: to.hash }
    }
    return { top: 0 }
  }
})


const pipeline = new Pipeline()
router.beforeEach(pipeline.handle.bind(pipeline))

export default router
