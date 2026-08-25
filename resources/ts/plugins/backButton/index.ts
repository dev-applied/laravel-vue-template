import BackButton from "@/plugins/backButton/BackButton.vue"
import createMixin from "@/plugins/backButton/mixin"
import type { RouteLocationRaw } from "vue-router"
import type {App, ComponentOptions} from "vue"


const defaultOptions = {
  rootKey: "$root",
  keyName: "backButton"
}

const state: BackButton.State = {
  link: null,
  text: null,
  vms: []
}

export const backButton: BackButton.Plugin = {
  registerInstance(vm: typeof BackButton) {
    state.vms.push(vm)
    vm.link = state.link
    vm.text = state.text
  },
  unregisterInstance(vm: typeof BackButton) {
    // @ts-ignore
    state.vms.splice(state.vms.findIndex(vm), 1)
  },
  setLink(link: RouteLocationRaw | null) {
    state.link = link
    state.vms.forEach(function(vm) {
      vm.link = link
    })
  },
  setText(text: string | null) {
    state.text = text
    state.vms.forEach(function(vm) {
      vm.text = text
    })
  }
}


export default {
  install(app: App, options: typeof defaultOptions | void) {
    app.config.globalProperties.$backButton = backButton
    app.component("BackButton", BackButton)
    // `defineComponent` returns a DefineComponent, which app.mixin does not
    // accept directly even though it is exactly a component options object.
    // Cast at the boundary rather than dropping defineComponent, which is
    // what gives the mixin's computed and watch a typed `this`.
    app.mixin(createMixin(backButton, Object.assign(defaultOptions, options)) as ComponentOptions)
  }
}
