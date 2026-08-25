import {computed, ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export interface SavedView {
  id:        number
  key:       string
  name:      string
  payload:   Record<string, unknown>
  isDefault: boolean
  isShared:  boolean
  position:  number
  /** False for a view a colleague shared — applying is fine, editing is not. */
  isOwn:     boolean
  ownerName?: string | null
  updatedAt?: string | null
}

/**
 * Named filter sets for one screen.
 *
 * The payload is opaque to this module: whatever the screen hands to `save()`
 * comes back from `apply()` unchanged. That is what lets one picker serve a
 * table with three filters and another with twelve.
 *
 *   const {filters} = useFilters({search: "", status: null})
 *   const views = useSavedViews('items.index')
 *
 *   <AppSavedViews
 *     :views="views"
 *     :current="{filters, sortBy, itemsPerPage}"
 *     @apply="state => Object.assign(filters, state.filters)"
 *   />
 */
export default function useSavedViews(key: string) {
  const {$http, $error} = useHttp()

  const views   = shallowRef<SavedView[]>([])
  const loading = ref(false)
  const loaded  = ref(false)

  const mine        = computed(() => views.value.filter((v) => v.isOwn))
  const shared      = computed(() => views.value.filter((v) => !v.isOwn))
  const defaultView = computed(() => views.value.find((v) => v.isOwn && v.isDefault) ?? null)

  async function fetch(): Promise<void> {
    loading.value = true

    const response = await $http.get('/saved-views', {params: {key}}).catch((e: any) => e)

    loading.value = false
    // Silent: a failure to load the picker should not block the table behind it.
    if ($error(response.status, response.data?.message, response.data?.errors, false)) return

    views.value = response.data.views
    loaded.value = true
  }

  async function save(name: string, payload: Record<string, unknown>, options: {isDefault?: boolean, isShared?: boolean} = {}): Promise<SavedView | null> {
    const response = await $http.post('/saved-views', {
      key,
      name,
      payload,
      is_default: options.isDefault ?? false,
      is_shared:  options.isShared ?? false,
    }).catch((e: any) => e)

    // Loud: the person just typed a name and pressed Save. A duplicate name
    // comes back as a 422 they need to see.
    if ($error(response.status, response.data?.message, response.data?.errors)) return null

    await fetch()

    return response.data
  }

  async function update(view: SavedView, changes: Partial<{name: string, payload: Record<string, unknown>, is_default: boolean, is_shared: boolean}>): Promise<boolean> {
    const response = await $http.put(`/saved-views/${view.id}`, changes).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    await fetch()

    return true
  }

  async function remove(view: SavedView): Promise<boolean> {
    const response = await $http.delete(`/saved-views/${view.id}`).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    views.value = views.value.filter((v) => v.id !== view.id)

    return true
  }

  return {views, mine, shared, defaultView, loading, loaded, fetch, save, update, remove}
}
