declare module "axios" {
  import type {AxiosResponse} from "@/composables/axios"

  export interface AxiosInstance {
    download: <T = any, R = AxiosResponse<T>>(url: string, params?: any, method?: string) => Promise<R>
  }
}
export {}
