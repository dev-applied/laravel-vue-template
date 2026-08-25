import {type PropType, ref} from 'vue'
import axios, {type AxiosInstance} from 'axios'
import {$http} from "@/plugins/axios"
import errorHandler from '@/plugins/errorHandler'

import type {AutocompleteItem, CreateItemPayload, CreateItemResponse} from './types'

interface UseCreateItemOptions {
  endpoint: string
  newItemKey: string
  axios?: AxiosInstance
}


export function makeUseCreateItemProps() {
  return {
    createEndpoint: { type: String, required: true },
    axios: { type: Object as PropType<AxiosInstance>, default: axios },
  }
}

export function useCreateItem<T = any>(options: UseCreateItemOptions) {
  const { endpoint, axios: customAxios, newItemKey } = options
  // $http, not the bare axios package: the package default has no baseURL,
  // no auth header and none of the app's interceptors, so a create fired
  // through it went out unauthenticated to a relative URL.
  const http = customAxios || $http

  const creating = ref(false)

  /**
   * Create a new item on the server.
   * Returns an object with `item` (or null) and `error` (true if an exception occurred).
   */
  async function create(name: string): Promise<{ item: AutocompleteItem<T> | null; error: boolean }> {
    if (!name) return { item: null, error: false }

    creating.value = true
    try {
      const payload: CreateItemPayload = { name }
      const { data } = await http.post<CreateItemResponse<T>>(endpoint, payload)

      const newItem = data?.[newItemKey] ?? null
      return { item: (newItem as AutocompleteItem<T>) ?? null, error: false }
    } catch (err: any) {
      if (!axios.isCancel(err)) {
        const status = err?.status
        const data = err?.data
        const message = data?.message || err?.message
        const errors = data?.errors

        errorHandler(status, message, errors)
      }
      return { item: null, error: true }
    } finally {
      creating.value = false
    }
  }

  return {
    creating,
    create,
  }
}

