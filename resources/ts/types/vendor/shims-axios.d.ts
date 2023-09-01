import type { AxiosResponse } from "@/plugins/axios"

declare module "axios" {
  export interface AxiosStatic {
    download: <T = any, R = AxiosResponse<T>>(url: string, params?: any, method?: string) => Promise<R>
  }
}
export {}
