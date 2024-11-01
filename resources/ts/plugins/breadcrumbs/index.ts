import createMixin from "@/plugins/breadcrumbs/mixin"
import BreadCrumbs from "@/plugins/breadcrumbs/BreadCrumbs.vue"


const defaultOptions: Breadcrumbs.Options = {
  keyName: "breadCrumbs"
}

export const breadCrumbs: Breadcrumbs.Plugin = {
  registerInstance(vm: any) {
    state.vms.push(vm)
    vm.items = state.items
  },
  unregisterInstance(vm: any) {
    const index = state.vms.findIndex((v) => v === vm)
    if (index !== -1) {
      state.vms.splice(index, 1)
    }
  },
  setItems(items: any[]) {
    state.items = items
    state.vms.forEach((vm) => {
      vm.items = items
    })
  }
}

const state: Breadcrumbs.State = {
  items: [],
  vms: []
}

function install(app, options?: any): void {
  options = Object.assign(defaultOptions, options)
  app.config.globalProperties[`$${options.keyName}`] = breadCrumbs
  app.component("BreadCrumbs", BreadCrumbs)
  app.mixin(createMixin(breadCrumbs, options))
}


export default {install}
