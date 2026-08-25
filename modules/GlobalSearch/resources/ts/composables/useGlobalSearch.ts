import {ref, type Ref} from "vue"

/**
 * Shared open/close state for the one palette.
 *
 * Module scope, not per-component: the palette is mounted once in the layout,
 * and every toolbar button, empty-state link and keyboard shortcut has to reach
 * that same instance. A per-component ref would give each caller its own hidden
 * dialog and nothing would appear to happen.
 */
const open = ref(false)
const initialQuery = ref("")

export interface GlobalSearchApi {
  open: Ref<boolean>
  initialQuery: Ref<string>
  openSearch: (query?: string) => void
  closeSearch: () => void
}

export function useGlobalSearch(): GlobalSearchApi {
  return {
    open,
    initialQuery,
    openSearch(query = "") {
      initialQuery.value = query
      open.value = true
    },
    closeSearch() {
      open.value = false
    },
  }
}
