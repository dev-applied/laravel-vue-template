import createMixin from "@/plugins/breadcrumbs/mixin"
import BreadCrumbs from "@/plugins/breadcrumbs/BreadCrumbs.vue"


const defaultOptions: Breadcrumbs.Options = {
  rootKey: "$root",
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

function install(Vue: any, options?: any): void {
  options = Object.assign(defaultOptions, options)
  Vue.prototype[`$${options.keyName}`] = breadCrumbs
  Vue.component("BreadCrumbs", BreadCrumbs)
  Vue.mixin(createMixin(breadCrumbs, options))
}


export default install
