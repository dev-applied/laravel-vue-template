import { ref, toValue, type MaybeRef } from "vue"
import axios from "axios"
import errorHandler from "@/plugins/errorHandler"

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
    const statusMsg = data.data.length === 0 || !data.next_page_url ? 'empty' : 'ok'

    return { status: statusMsg, data: data.data }
  }

  return {
    pagination,
    setPagination,
    loadData,
  }
}
