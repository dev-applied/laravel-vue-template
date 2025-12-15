import type {AxiosResponse as Response} from "axios"
import axios from "axios"
import router from "@/router"
import {useUserStore} from "@/stores/user"
import {$error} from "@/plugins/errorHandler"
import {ROUTES} from "@/router/paths"
import {type App} from "vue"
import {errorLogger, requestLogger, responseLogger} from 'axios-logger'

export type AxiosResponse<T = object> = Response<T & { errors?: string[] }>

export interface AxiosPaginationResponse<ItemType> extends AxiosResponse {
  data: {
    total: number
    per_page: number
    current_page: number
    last_page: number
    first_page_url: string
    last_page_url: string
    next_page_url?: string
    prev_page_url?: string
    path: string
    from: number
    to: number
    data: ItemType[]
    errors?: string[]
  }
}

export const $http = axios.create({
  headers: {
    common: {
      Accept: "application/json",
      "Content-Type": "application/json"
    }
  },
  baseURL: `${import.meta.env.VITE_APP_URL}${import.meta.env.VITE_API_BASE_URL}`
})

$http.interceptors.request.use((config) => {
  if (localStorage.getItem("token")) {
    config.headers["Authorization"] = "bearer " + localStorage.getItem("token")
  }

  if (config.data instanceof FormData) {
    config.headers['Content-Type'] = 'multipart/form-data'
  }

  return config
})

if (import.meta.env.DEV) {
  $http.interceptors.request.use(requestLogger, errorLogger)
  $http.interceptors.response.use(responseLogger, errorLogger)
}

$http.interceptors.response.use(
  function (response) {
    return response
  },
  function ({response, message}) {
    if (!response) {
      return Promise.reject({status: 500, data: {message}})
    }
    if (
      response.status === 401 ||
      response.data.message === "Authentication is required to continue"
    ) {
      if (router.currentRoute.value.name !== ROUTES.LOGIN) {
        const userStore = useUserStore()
        userStore.logout().then(() => {
          router
            .push({name: ROUTES.LOGIN, query: {to: router.currentRoute.value.fullPath}})
            .catch((e: Error) => e)
        })
      }
    }
    return Promise.reject(response)
  }
)

$http.download = async function <T = any, R = AxiosResponse<T>>(url: string, params = {}, method = "get"): Promise<R> {
  const response = await this.request({
    url,
    method,
    params: method === "get" ? params : null,
    data: method !== "get" ? params : null,
    responseType: "blob"
  }).catch(e => e)

  if (response.status > 299) {
    const jsonData = await new Response(response.data).json()
    if ($error(response.status, jsonData.message)) return response
  }

  // Get File Name
  const headerLine = response.headers["content-disposition"]
  let startFileNameIndex = headerLine.indexOf("filename=")
  if (startFileNameIndex !== -1) {
    startFileNameIndex += 9
  }
  const endFileNameIndex = headerLine.length
  let filename = headerLine.substring(startFileNameIndex, endFileNameIndex).replace("\"", "")
  filename = filename.replace("\"", "")

  // Download the file
  const href = URL.createObjectURL(response.data)
  const link = document.createElement("a")
  link.href = href
  link.setAttribute("download", filename)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(href)

  return response
}

export default {
  install(app: App) {
    app.config.globalProperties.$http = $http
  }
}
