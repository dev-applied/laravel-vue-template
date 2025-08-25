import {type App} from "vue"
import routeTo from "@/plugins/routeTo.ts"
import {downloadFile, fileUrl, formatFileSize} from "@/plugins/file"
import {$error} from "@/plugins/errorHandler"
import BackButton from "@/plugins/backButton/index"
import BreadCrumbs from "@/plugins/breadcrumbs/index"
import axios from "@/plugins/axios"
import auth from "@/plugins/auth"
import Confirm from "@/plugins/confirm"
import {ROUTES} from "@/router/paths"
import {createHead, VueHeadMixin} from '@unhead/vue/client'

export function usePlugins(app: App) {
  // Global Mixins
  app.mixin(VueHeadMixin)

  // Plugins
  app.use(axios)
  app.use(auth)
  app.use(BackButton)
  app.use(BreadCrumbs)
  app.use(Confirm)
  app.use(createHead())

  app.config.globalProperties.$error = $error
  app.config.globalProperties.$routeTo = routeTo
  app.config.globalProperties.$file = {
    url: fileUrl,
    download: downloadFile,
    formatFileSize: formatFileSize
  }
  app.config.globalProperties.ROUTES = ROUTES
}
