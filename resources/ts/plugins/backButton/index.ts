import BackButton from "@/plugins/backButton/BackButton.vue"
import createMixin from "@/plugins/backButton/mixin"
import type { RawLocation } from "vue-router"


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


function install(Vue: any, options: any) {
  Vue.prototype.$backButton = backButton
  Vue.component("BackButton", BackButton)
  Vue.mixin(createMixin(backButton, Object.assign(defaultOptions, options)))
}

export default install
