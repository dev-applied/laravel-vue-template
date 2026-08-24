import {Auth} from "@/plugins/auth"
import {AxiosInstance} from "axios"
import {ROUTES} from "@/router/paths"
import type {Vuetify} from "vuetify"
import type {
  LocationQueryRaw,
  RouteLocationNormalizedLoaded,
  RouteLocationRaw,
  RouteParamsGeneric,
  Router
} from "vue-router"
import type {VBtn} from "vuetify/lib/components/VBtn"

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    $auth: Auth
    $error: (
      status: number,
      message = "Unknown Error",
      errors: boolean | any = false,
      notify = true
    ) => boolean
    $routeTo: (name: string, params?: RouteParamsGeneric, query?: LocationQueryRaw) => RouteLocationRaw
    ROUTES: typeof ROUTES
    $vuetify: Vuetify
    $router: Router
    $route: RouteLocationNormalizedLoaded
    $http: AxiosInstance & { download: (url: string, params = {}, method = "get") => void }
    $confirm: (message: string, title = "Confirm", options = {}) => Promise<boolean>
  }

  interface GlobalComponents {
    VBtnPrimary: typeof VBtn
    VBtnSecondary: typeof VBtn
    VBtnTertiary: typeof VBtn
  }
}
