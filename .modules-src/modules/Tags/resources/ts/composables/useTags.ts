import {ref, shallowRef} from "vue"
import useHttp from "@/composables/useHttp"

export interface Tag {
  id:         number
  name:       string
  slug:       string
  color?:     string | null
  type?:      string | null
  usageCount?: number
  usage_count?: number
}

/**
 * The tag pool — what the autocomplete offers and what a filter lists.
 *
 * `type` scopes the pool: pass the same value the model's `tagType()` returns,
 * or omit it for the global pool. Passing the wrong one silently offers tags
 * that will never match anything.
 */
export default function useTags(type: string | null = null) {
  const {$http, $error} = useHttp()

  const tags    = shallowRef<Tag[]>([])
  const loading = ref(false)
  const loaded  = ref(false)

  async function fetch(search = ''): Promise<void> {
    loading.value = true

    const response = await $http.get('/tags', {
      params: {type: type ?? undefined, search: search || undefined},
    }).catch((e: any) => e)

    loading.value = false
    // Silent: a failing pool should degrade the autocomplete to free text,
    // not put an error in front of someone mid-typing.
    if ($error(response.status, response.data?.message, response.data?.errors, false)) return

    tags.value = response.data.tags
    loaded.value = true
  }

  return {tags, loading, loaded, fetch}
}

/**
 * Tags on one record.
 */
export function useRecordTags(type: string, id: number | string) {
  const {$http, $error} = useHttp()

  const tags    = shallowRef<Tag[]>([])
  const saving  = ref(false)

  const base = `/tags/${type}/${id}`

  async function fetch(): Promise<void> {
    const response = await $http.get(base).catch((e: any) => e)
    if ($error(response.status, response.data?.message, response.data?.errors, false)) return

    tags.value = response.data.tags
  }

  async function sync(names: string[]): Promise<boolean> {
    saving.value = true

    const response = await $http.put(base, {tags: names}).catch((e: any) => e)

    saving.value = false
    if ($error(response.status, response.data?.message, response.data?.errors)) return false

    // Take the server's list: it is the one that normalised the names, so the
    // chips show the canonical tag rather than what was typed.
    tags.value = response.data.tags

    return true
  }

  return {tags, saving, fetch, sync}
}
