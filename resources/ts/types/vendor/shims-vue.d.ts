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
import type {ConfirmOptions} from "@/plugins/confirm"

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
    /**
     * Kernel route names, plus whatever the installed modules merged in.
     *
     * The intersection is the point. `typeof ROUTES` alone is the kernel's
     * static object literal, so every module route name — which paths.ts adds
     * at runtime via Object.assign over the routes.ts glob — was a type error
     * at every call site, in a codebase whose rule is "never inline a route
     * string". The Record half accepts those; the typeof half keeps
     * autocomplete and typo-checking for the kernel's own names.
     *
     * It does NOT make a missing name safe: $routeTo throws on a name it
     * cannot resolve, so a typo still fails loudly at runtime.
     */
    ROUTES: typeof ROUTES & Record<string, string>
    $vuetify: Vuetify
    $router: Router
    $route: RouteLocationNormalizedLoaded
    $http: AxiosInstance & { download: (url: string, params = {}, method = "get") => void }
    // Matches plugins/confirm/index.ts `show()`. The previous declaration had
    // the first two parameters in the OPPOSITE order (message, title) and
    // omitted `color` entirely, so every call site was typed against a
    // signature the implementation never had.
    $confirm: (
      title: string,
      message?: string | Partial<ConfirmOptions>,
      color?: string,
      options?: Partial<ConfirmOptions>
    ) => Promise<boolean>
  }

  interface GlobalComponents {
    VBtnPrimary: typeof VBtn
    VBtnSecondary: typeof VBtn
    VBtnTertiary: typeof VBtn
  }
}
