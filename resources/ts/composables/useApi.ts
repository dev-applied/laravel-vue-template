import { onMounted, ref, type Ref } from "vue"
import { $http } from "@/plugins/axios"
import type { AxiosRequestConfig } from "axios"

export interface UseApiOptions<T> {
  /** HTTP method. Default "get". */
  method?:    "get" | "post" | "put" | "patch" | "delete"
  /** Body / params for the request. */
  config?:    AxiosRequestConfig
  /** Skip the initial fetch on mount. */
  immediate?: boolean
  /** Default value returned while loading or after an error. */
  initial?:   T | null
  /** Called on success. Useful for side-effects after the data lands. */
  onSuccess?: (data: T) => void
  /** Called when the request errors out. Receives the response (or undefined for network errors). */
  onError?:   (err: unknown) => void
}

export interface UseApiReturn<T> {
  data:    Ref<T | null>
  error:   Ref<unknown | null>
  loading: Ref<boolean>
  /** Re-run the request. Resolves with the new data (or null on error). */
  refresh: () => Promise<T | null>
}

/**
 * Typed wrapper around $http with reactive data / error / loading state.
 *
 *   const { data: user, loading, error, refresh } = useApi<User>("/auth")
 *
 * Cuts the boilerplate around every "fetch on mount, show loader, surface
 * error, expose refresh" page. Pages just bind `loading` to spinners and
 * iterate `data` once it's there.
 */
export function useApi<T = unknown>(
  endpoint: string,
  options: UseApiOptions<T> = {},
): UseApiReturn<T> {
  const data    = ref<T | null>(options.initial ?? null) as Ref<T | null>
  const error   = ref<unknown | null>(null)
  const loading = ref(false)

  const method    = options.method ?? "get"
  const immediate = options.immediate ?? true

  async function refresh(): Promise<T | null> {
    loading.value = true
    error.value   = null
    try {
      // axios signature differs for body vs no-body verbs; use the per-method
      // call so type narrowing works.
      let response
      switch (method) {
        case "get":    response = await $http.get   (endpoint, options.config); break
        case "delete": response = await $http.delete(endpoint, options.config); break
        case "post":   response = await $http.post  (endpoint, options.config?.data, options.config); break
        case "put":    response = await $http.put   (endpoint, options.config?.data, options.config); break
        case "patch":  response = await $http.patch (endpoint, options.config?.data, options.config); break
      }

      data.value = response.data as T
      options.onSuccess?.(response.data as T)
      return response.data as T
    } catch (e) {
      error.value = e
      options.onError?.(e)
      return null
    } finally {
      loading.value = false
    }
  }

  if (immediate) {
    onMounted(() => { void refresh() })
  }

  return { data, error, loading, refresh }
}
