import {type App} from "vue"
import routeTo from "@/plugins/routeTo.ts"
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
  app.config.globalProperties.ROUTES = ROUTES

  // Module plugins. A module MAY ship modules/<Name>/resources/ts/plugin.ts
  // with a default export taking the Vue app — that is how it registers a
  // global property, a Vue plugin, or a mixin. Mirrors the routes.ts glob in
  // router/paths.ts: the glob is build-time, so dropping a module directory in
  // or deleting it is picked up on the next dev-server restart / build with no
  // config edits. Installed last so a module can depend on $http / $auth /
  // $error already being present.
  const modulePlugins = import.meta.glob<{ default?: (app: App) => void }>(
    '/modules/*/resources/ts/plugin.ts',
    {eager: true}
  )
  for (const modulePlugin of Object.values(modulePlugins)) {
    modulePlugin.default?.(app)
  }
}
