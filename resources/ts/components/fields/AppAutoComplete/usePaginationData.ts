import {computed, ref} from "vue"

export interface PaginationState {
  currentPage: number;
  lastPage: number;
  total: number;
  perPage: number;
}

export function usePaginationData(initialItemsPerPage: number) {
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const perPage = ref(initialItemsPerPage)

  const loading = ref(false)
  const finished = computed(() => currentPage.value >= lastPage.value)

  /**
   * Called when new paginated data arrives from the API.
   */
  function updatePagination(meta: PaginationState) {
    currentPage.value = meta.currentPage
    lastPage.value = meta.lastPage
    total.value = meta.total
    perPage.value = meta.perPage
  }

  /**
   * Move to the next page (if available).
   */
  function goToNextPage() {
    if (finished.value) return
    currentPage.value += 1
  }

  /**
   * Reset pagination state when user starts a new search.
   */
  function resetPagination() {
    currentPage.value = 1
    lastPage.value = 1
    total.value = 0
  }

  return {
    // state
    currentPage,
    lastPage,
    total,
    perPage,
    loading,
    finished,

    // methods
    updatePagination,
    goToNextPage,
    resetPagination,
  }
}
