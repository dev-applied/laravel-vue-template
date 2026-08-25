import {ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export interface FavoriteRecord {
  id: number | string
  label: string
}

export interface FavoriteItem {
  id: number
  type: string | null
  favoritedAt: string
  record: FavoriteRecord | null
}

/**
 * The current user's favourites list.
 *
 * Read-only here on purpose — starring lives on AppFavoriteButton, next to the
 * thing being starred. A list that could also toggle would need to stay in sync
 * with every button on the page, and there is no reason to pay for that.
 */
export default function useFavorites(type?: string) {
  const {$http, $error} = useHttp()

  const favorites = ref<FavoriteItem[]>([])
  const loading   = ref(false)
  const loaded    = ref(false)

  async function fetch(): Promise<void> {
    loading.value = true

    const response = await $http
      .get("/favorites", {params: type ? {type} : {}})
      .catch((e: any) => e)

    loading.value = false

    if ($error(response.status, response.data?.message, response.data?.errors)) return

    favorites.value = response.data.data ?? []
    loaded.value    = true
  }

  /** Drop a row locally after the button on it un-stars. */
  function forget(id: number): void {
    favorites.value = favorites.value.filter((f) => f.id !== id)
  }

  return {favorites, loading, loaded, fetch, forget}
}

/**
 * The toggle, for one record.
 *
 * `pending` guards the double-tap: the endpoint is a toggle, so two in-flight
 * requests would cancel each other out and leave the star showing the opposite
 * of the truth.
 */
export function useFavoriteToggle(type: string, id: number | string, initial = false) {
  const {$http, $error} = useHttp()

  const favorited = shallowRef(initial)
  const pending   = ref(false)

  async function toggle(): Promise<void> {
    if (pending.value) return

    pending.value = true

    const response = await $http.post(`/favorites/${type}/${id}`).catch((e: any) => e)

    pending.value = false

    if ($error(response.status, response.data?.message, response.data?.errors)) return

    // The server's answer, not a local flip — it is the one that knows whether
    // the row existed.
    favorited.value = !!response.data?.favorited
  }

  return {favorited, pending, toggle}
}
