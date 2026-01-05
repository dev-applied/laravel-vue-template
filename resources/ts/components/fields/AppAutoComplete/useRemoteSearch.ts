import {computed, type PropType, ref, unref} from "vue"
import {$http} from "@/plugins/axios"
import axios, {type AxiosInstance} from "axios"
import errorHandler from "@/plugins/errorHandler"

import type {AutocompleteItem, LaravelPaginatorResponse, PaginatedResult, SearchQueryParams,} from "./types"

import {isEqualId, normalizeItems} from "./utils"

interface PaginationState {
  currentPage: number
  lastPage: number
  total: number
  perPage: number
}

interface UseRemoteSearchOptions<_T = any> {
  endpoint: string;
  itemsPerPage: number;
  filters?: any;
  // minSearchChars?: number;
  axios?: AxiosInstance;
}


export function makeUseRemoteSearchProps() {
  return {
    endpoint: {type: String as PropType<string>, required: true},
    itemsPerPage: {type: Number, default: 10},
    filters: {type: [Object, Function] as PropType<any>, default: () => ({})},
    // minSearchChars: {type: Number, default: 0},
    searchFields: {type: String, default: "search"},
    debounceFilters: {type: Array as PropType<string[]>, default: () => ["search"]},
  }
}


export function useRemoteSearch<T = any>(options: UseRemoteSearchOptions<T>) {
  const {
    endpoint,
    itemsPerPage,
    filters = {},
    // minSearchChars = 0,
    axios: customAxios,
  } = options

  const http = customAxios || $http

  const items = ref<AutocompleteItem<T>[]>([])
  const loading = ref(false)
  const initialLoading = ref(false)
  const searchLoading = ref(false)

  const pagination = ref<PaginationState>({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: itemsPerPage,
  })

  let abortController: AbortController | null = null

  const canSearch = computed(() => {
    return itemsPerPage !== -1
  })

  function resetPagination() {
    pagination.value.currentPage = 1
    pagination.value.lastPage = 1
    pagination.value.total = 0
  }

  function buildQueryParams(pageOverride?: number): SearchQueryParams {
    const currentFilters = typeof filters === 'function' ? filters() : (unref(filters) || {})
    return {
      page: pageOverride ?? pagination.value.currentPage,
      itemsPerPage: itemsPerPage,
      ...currentFilters
    }
  }

  function cancelPreviousRequest() {
    abortController?.abort()
    abortController = null
  }

  function parseResponse(response: LaravelPaginatorResponse<T>): PaginatedResult<T> {
    return {
      items: normalizeItems(response.data),
      currentPage: response.current_page,
      lastPage: response.last_page,
      total: response.total,
      perPage: Number(response.per_page),
    }
  }

  async function fetchPage(pageOverride?: number): Promise<PaginatedResult<T>> {
    cancelPreviousRequest()
    abortController = new AbortController()

    const params = buildQueryParams(pageOverride)

    const {data} = await http.get<LaravelPaginatorResponse<T>>(endpoint, {
      params,
      signal: abortController.signal,
    })

    return parseResponse(data)
  }

  // function isSearchInvalid(): boolean {
  //   // If no limit is set (0), everything is valid (including empty string)
  //   if (minSearchChars <= 0) return false
  //
  //   const currentFilters = typeof filters === 'function' ? filters() : (unref(filters) || {})
  //   const term = String(currentFilters.search || "").trim()
  //
  //   // EXCEPTION: If we have selected items, we must always allow the query
  //   // This handles the case where user has selections but search is below minimum
  //   // or when component loads with pre-existing model (0 chars but selected items exist)
  //   const selected = currentFilters.selected || []
  //   if (Array.isArray(selected) && selected.length > 0) {
  //     return false // Allow search even if below minimum
  //   }
  //
  //   // STRICT CHECK: Block searches below minimum character count
  //   return term.length < minSearchChars
  // }

  function handleApiError(err: any) {
    if (axios.isCancel(err)) return

    const status = err.status
    const data = err.data
    const message = data?.message || err.message
    const errors = data?.errors

    errorHandler(status, message, errors)
  }

  // function clear() {
  //   items.value = []
  //   resetPagination()
  //   loading.value = false
  // }

  async function reload() {
    // if (isSearchInvalid()) {
    //   // Clear items to show "waiting for input" state
    //   clear()
    //   return
    // }

    loading.value = true
    initialLoading.value = true
    resetPagination()

    try {
      const result = await fetchPage(1)

      pagination.value.currentPage = result.currentPage
      pagination.value.lastPage = result.lastPage
      pagination.value.total = result.total
      pagination.value.perPage = result.perPage

      // PURE DATA ONLY: No prepend/append mixing here
      items.value = result.items
    } catch (err) {
      handleApiError(err)
    } finally {
      loading.value = false
      initialLoading.value = false
    }
  }

  async function loadNextPage() {
    if (loading.value) return
    if (pagination.value.currentPage >= pagination.value.lastPage) return

    // if (isSearchInvalid()) return

    loading.value = true
    searchLoading.value = true

    try {
      const nextPage = pagination.value.currentPage + 1
      const result = await fetchPage(nextPage)

      pagination.value.currentPage = result.currentPage
      pagination.value.lastPage = result.lastPage
      pagination.value.total = result.total

      // Filter duplicates in case API returns overlapping data
      const newItems = result.items.filter(newItem =>
        !items.value.some(existing => isEqualId(existing.id, newItem.id))
      )

      items.value = [...items.value, ...newItems]
    } catch (err) {
      handleApiError(err)
    } finally {
      loading.value = false
      searchLoading.value = false
    }
  }

  const finished = computed(() => pagination.value.currentPage >= pagination.value.lastPage)

  return {
    items,
    loading,
    initialLoading,
    searchLoading,
    pagination: {
      loading,
      finished
    },
    reload,
    // clear,
    loadNextPage,
    canSearch,
  }
}
