import { Auth } from '@/plugins/auth'
import { Axios } from 'axios'

declare module 'vue/types/vue' {
  interface Vue {
    $auth: Auth
    $noty: {
      success: (message: string) => void
      error: (message: string) => void
      warning: (message: string) => void
      info: (message: string) => void
    }
    $error: (
      status: number,
      message = 'Unknown Error',
      errors: boolean | any = false,
      notify = true
    ) => boolean
    $http: Axios
  }
}
