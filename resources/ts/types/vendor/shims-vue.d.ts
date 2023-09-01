import { Auth } from "@/plugins/auth"
import { Axios } from "axios"
import ROUTES from "@/router/paths"

declare module "vue/types/vue" {
  interface Vue {
    $auth: Auth
    $error: (
      status: number,
      message = "Unknown Error",
      errors: boolean | any = false,
      notify = true
    ) => boolean
    $http: Axios
    route: (name: string, params?: Dictionary<any>, query?: Dictionary<any>) => RawLocation
    ROUTES: ROUTES
    $file: (id: number, size: string = "thumbnail") => string
  }
}
