import Confirm from './ConfirmDialog.vue'
import type {App} from "vue"
import {createApp} from "vue"
import vuetify from "@/plugins/vuetify"

type defaultOptions = { property: string }

export type ConfirmOptions = {
  buttonTrueText: string
  buttonFalseText: string
  buttonTrueColor: string
  buttonFalseColor: string
  buttonFalseFlat: boolean
  buttonTrueFlat: boolean
  color: string
  icon: string
  message: string
  persistent: string
  showCancel: boolean
  title: string
  width: string | number
}

function createDialogCmp(options: Partial<ConfirmOptions>) {
  const container = document.querySelector('[data-app=true]') || document.body

  return new Promise(resolve => {
    const div = document.createElement('div')
    const confirmDialog = createApp(Confirm, {
      ...options,
      onClose: (value: boolean) => {
        container.removeChild(div)
        confirmDialog.unmount()
        resolve(value)
      }
    })


    confirmDialog.use(vuetify)
    confirmDialog.mount(div)
    container.appendChild(div)
  })
}

function show(title: string, message: string | ConfirmOptions | undefined = undefined, color: string = "warning", options: Partial<ConfirmOptions> = {}) {
  if (typeof message === "object") {
    options = message as ConfirmOptions
    options.message = title
  } else {
    options.color = color
    options.title = title
    options.message = message
  }

  return createDialogCmp(options)
}

function install(app: App, options?: defaultOptions) {
  const property = options?.property || '$confirm'
  app.config.globalProperties[property] = show
}


export default install
export const $confirm = show
