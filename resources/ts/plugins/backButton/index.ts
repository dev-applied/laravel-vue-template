import BackButton from "@/plugins/backButton/BackButton.vue"
import createMixin from "@/plugins/backButton/mixin"
import type { RawLocation } from "vue-router"
import type {App} from "vue"


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
  setLink(link: RawLocation | null) {
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
    app.mixin(createMixin(backButton, Object.assign(defaultOptions, options)))
  }
}
