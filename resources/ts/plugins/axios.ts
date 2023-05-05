import Vue from 'vue'
import axios from 'axios'
import router from '@/router'
import { useUserStore } from '@/stores/user'
import type { AxiosResponse as Response } from 'axios'

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

Vue.prototype.$http = axios

axios.defaults.headers.common = {
  Accept: 'application/json',
  'Content-Type': 'application/json'
}
axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL

axios.interceptors.request.use((config) => {
  if (localStorage.getItem('token')) {
    config.headers['Authorization'] = 'bearer ' + localStorage.getItem('token')
  }

  return config
})

axios.interceptors.response.use(
  function (response) {
    return response
  },
  function ({ response, message }) {
    if (!response) {
      return Promise.reject({ status: 500, data: { message } })
    }
    if (
      response.status === 401 ||
      response.data.message === 'Authentication is required to continue'
    ) {
      if (router.currentRoute.path !== '/') {
        const userStore = useUserStore()
        userStore.logout().then(() => {
          router
            .push({ name: 'Login', query: { to: router.currentRoute.fullPath } })
            .catch((e: Error) => e)
        })
      }
    }
    return Promise.reject(response)
  }
)
