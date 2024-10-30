import { Auth } from "@/plugins/auth"
import { Axios } from "axios"
import { ROUTES } from "@/router/paths"
import type { LocationQueryRaw, RouteLocationRaw, RouteParamsGeneric } from "vue-router"

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
    $file: {
      url: (id: number, size: string = "thumbnail") => string,
      download: (id: number, size: string = "thumbnail") => Promise<any>
    }
    $http: Axios & { download: (url: string, params = {}, method = "get") => void }
    $confirm: (message: string, title = "Confirm", options = {}) => Promise<boolean>
  }
}

export {}
