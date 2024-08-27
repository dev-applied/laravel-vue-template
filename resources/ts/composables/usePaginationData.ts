import { ref, toValue, type MaybeRef, watch } from "vue"
import axios from "axios"
import errorHandler from "@/plugins/errorHandler"
import { useDebounceFn } from "@vueuse/core"
import { cloneDeep } from "lodash"

export type Methods = "GET" | "POST" | "PUT" | "PATCH"
export type InfiniteScrollStatus = "ok" | "empty" | "error" | "canceled"

export interface PaginationData {
  page: number
  itemsPerPage: number
  sortBy: Record<string, any>
}

export default function usePaginationData<T = any>(
  endpoint: MaybeRef<string>,
  filters: MaybeRef<Record<string, any>> = {},
  method: MaybeRef<Methods> = "GET"
) {
  let abortController: AbortController | null = null

  const pagination = ref<PaginationData & { lastPage: number, total: number }>({
    page: 1,
    itemsPerPage: 10,
    sortBy: {},
    lastPage: 0,
    total: 0,
  })

  function setPagination(newPagination: Partial<PaginationData>) {
    if (newPagination.page) {
      pagination.value.page = newPagination.page
    }
    if (newPagination.itemsPerPage) {
      pagination.value.itemsPerPage = newPagination.itemsPerPage
    }
    if (newPagination.sortBy) {
      pagination.value.sortBy = newPagination.sortBy
    }
  }

  async function loadData(): Promise<{ status: InfiniteScrollStatus, data: T[], error?: string }> {
    abortController?.abort("canceled")
    abortController = new AbortController()

    let config: any = {
      page: pagination.value.page,
      itemsPerPage: pagination.value.itemsPerPage,
      sortBy: pagination.value.sortBy,
      ...toValue(filters)
    }

    if (toValue(method) === "GET") {
      config = {
        params: config
      }
    }

    const { status, data } = await axios[toValue(method).toLowerCase()](toValue(endpoint), config).catch((e: any) => e)

    const hasError = errorHandler(status, data.message, data.errors, false)
    if (hasError && data.message !== "canceled") {
      return { status: "error", data: [], error: data.message }
    } else if (hasError) {
      return { status: "canceled", data: [] }
    }

    pagination.value.total = data.total
    const statusMsg = data.data.length === 0 ? "empty" : "ok"
    return { status: statusMsg, data: data.data }
  }

  async function reload(resetPage = true) {
    pagination.value.lastPage = 0
    pagination.value.page = resetPage ? 1 : pagination.value.page
    return await loadData()
  }

  const debounceReload = useDebounceFn(() => {
    reload().then()
  }, 1000)

  let oldFilters = cloneDeep(toValue(filters))
  watch(() => filters, (newValue) => {
    newValue = toValue(newValue)

    if (JSON.stringify(newValue) === JSON.stringify(oldFilters)) {
      return
    }
    if (newValue?.search !== oldFilters?.search) {
      debounceReload().then()
    } else {
      reload().then()
    }
    oldFilters = cloneDeep(newValue)
  }, { deep: true })

  watch(() => toValue(endpoint), () => {
    reload().then()
  })

  return {
    pagination,
    setPagination,
    loadData,
    reload
  }
}
