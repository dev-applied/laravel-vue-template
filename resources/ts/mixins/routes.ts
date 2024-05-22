import { ROUTES } from "@/router/paths"
import { defineComponent } from "vue"
import type { RawLocation } from "vue-router"
import type { Dictionary } from "vue-router/types/router"

export default defineComponent({
  data() {
    return {
      ROUTES: ROUTES
    }
  },
  methods: {
    route(name: string, params: Dictionary<any> = {}, query: Dictionary<any> = {}): RawLocation {
      const routes = this.$router!.options!.routes!.map((route) => [route, ...route.children || []]).flat()
      const route = routes.find((route) => route.name === name)
      if (!route) throw new Error(`Route ${name} not found`)
      return { name, params, query }
    }
  }
})
