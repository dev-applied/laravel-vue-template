import type {App, Component} from "vue"

// Vite no longer exposes ModuleNamespace at "vite/types/hot", and the glob only
// ever needs the default export's shape anyway.
type LayoutModule = { default?: Component & { name?: string } }

const layouts = import.meta.glob<true, string, LayoutModule>("./*.vue", { eager: true })

export function loadLayouts(app: App) {
  Object.entries(layouts).forEach(([fileName, layout]) => {
    // Skip rather than register `undefined` as a component: a .vue file in this
    // folder with no default export used to be registered as a broken
    // component whose failure only surfaced when a route tried to use it.
    if (!layout?.default) return

    const name = layout.default.name || fileName.replace(/(\.\/|\.vue)/g, "")
    app.component(name, layout.default)
  })
}
