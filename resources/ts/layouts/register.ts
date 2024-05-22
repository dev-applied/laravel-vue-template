import Vue from "vue"
import type { ModuleNamespace } from "vite/types/hot"

const layouts = import.meta.glob<true, string, ModuleNamespace>("./*.vue", { eager: true })

Object.entries(layouts).forEach(([fileName, layout]) => {
  const name = layout?.default?.name || fileName.replace(/(\.\/|\.vue)/g, "")
  Vue.component(name, layout?.default)
})
