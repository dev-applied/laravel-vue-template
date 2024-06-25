import type {App} from "vue"
import type { ModuleNamespace } from "vite/types/hot"

const layouts = import.meta.glob<true, string, ModuleNamespace>("./*.vue", { eager: true })

export function loadLayouts(app: App) {
  Object.entries(layouts).forEach(([fileName, layout]) => {
    const name = layout?.default?.name || fileName.replace(/(\.\/|\.vue)/g, "")
    app.component(name, layout?.default)
  })
}
