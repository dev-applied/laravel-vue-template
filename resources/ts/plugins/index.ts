import {type App} from "vue"
import route from "@/plugins/route"
import file from "@/plugins/file"
import errorHandler from "@/plugins/errorHandler"
import BackButton from "@/plugins/backButton/index"
import BreadCrumbs from "@/plugins/breadcrumbs/index"
import axios from "@/plugins/axios"
import auth from "@/plugins/auth"
import { ROUTES } from "@/router/paths"
import { createHead, VueHeadMixin } from '@unhead/vue'

export function usePlugins(app: App) {
  // Global Mixins
  app.mixin(VueHeadMixin)

  // Plugins
  app.use(axios)
  app.use(auth)
  app.use(BackButton)
  app.use(BreadCrumbs)
  app.use(createHead())

  app.config.globalProperties.$error = errorHandler
  app.config.globalProperties.$routeTo = route
  app.config.globalProperties.$fileUrl = file
  app.config.globalProperties.ROUTES = ROUTES
}
