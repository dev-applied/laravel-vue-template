import { computed, reactive, watch, type ComputedRef } from "vue"
import { useRoute, useRouter } from "vue-router"
import cloneDeep from "lodash.clonedeep"

export interface UseFiltersOptions<T> {
  /** Keys in `filters` that should NOT be synced to the URL (e.g. transient UI). */
  exclude?: (keyof T)[]
  /** Encoder for non-string values written to the URL. Defaults to String(). */
  serialize?: (value: unknown) => string
  /** Decoder for values read from the URL. Defaults to the initial-value type. */
  deserialize?: (value: string, key: keyof T, initial: T) => unknown
}

export interface UseFiltersReturn<T extends object> {
  /** Reactive filters object — bind to v-model. */
  filters: T
  /** Reset to the initial state (clears the URL too). */
  reset:   () => void
  /** True when ANY filter differs from its initial value. Useful to show a "clear" button. */
  isActive: ComputedRef<boolean>
}

/**
 * Reactive filter state that syncs to the URL query string. Refreshing the
 * page preserves filters; back/forward navigates between filter states; copy/
 * pasting a URL shares the exact view.
 *
 *   const { filters, reset, isActive } = useFilters({
 *     search: "",
 *     status: null as string | null,
 *     owner_id: null as number | null,
 *   })
 *
 *   <ItemFilterBar v-model="filters" />
 *   <AppPaginationTable endpoint="items" :filters="filters" />
 *   <v-btn v-if="isActive" @click="reset">Clear filters</v-btn>
 */
export function useFilters<T extends object>(
  initial: T,
  options: UseFiltersOptions<T> = {},
): UseFiltersReturn<T> {
  const route   = useRoute()
  const router  = useRouter()
  const exclude = new Set(options.exclude ?? [])

  // Snapshot initial for reset + comparison.
  const initialSnapshot = cloneDeep(initial)

  // Seed from URL where present.
  const seeded = { ...cloneDeep(initial) } as T
  for (const key of Object.keys(initial) as (keyof T)[]) {
    if (exclude.has(key)) continue
    const fromUrl = route.query[key as string]
    if (fromUrl === undefined) continue
    const raw = Array.isArray(fromUrl) ? fromUrl[0] : fromUrl
    if (raw === null) continue

    if (options.deserialize) {
      seeded[key] = options.deserialize(raw, key, initial) as T[keyof T]
    } else {
      // Best-effort default deserialization based on the initial value type
      const init = initial[key]
      if (typeof init === "number") seeded[key] = Number(raw) as T[keyof T]
      else if (typeof init === "boolean") seeded[key] = (raw === "true") as T[keyof T]
      else seeded[key] = raw as unknown as T[keyof T]
    }
  }

  const filters = reactive(seeded) as T

  watch(
    () => cloneDeep(filters),
    (newVal) => {
      const query: Record<string, string> = {}
      // Preserve any non-filter query keys (page=, etc. from other code paths)
      for (const [k, v] of Object.entries(route.query)) {
        if (k in initialSnapshot) continue
        if (v !== null && v !== undefined) query[k] = Array.isArray(v) ? (v[0] ?? "") : v
      }
      for (const key of Object.keys(newVal) as (keyof T)[]) {
        if (exclude.has(key)) continue
        const v = newVal[key]
        if (v === "" || v === null || v === undefined) continue
        if (Array.isArray(v) && v.length === 0) continue
        const same = JSON.stringify(v) === JSON.stringify((initialSnapshot as T)[key])
        if (same) continue
        query[key as string] = options.serialize ? options.serialize(v) : String(v)
      }
      void router.replace({ query })
    },
    { deep: true },
  )

  function reset() {
    for (const key of Object.keys(initialSnapshot) as (keyof T)[]) {
      ;(filters as T)[key] = cloneDeep((initialSnapshot as T)[key])
    }
  }

  const isActive = computed(() => {
    for (const key of Object.keys(initialSnapshot) as (keyof T)[]) {
      if (JSON.stringify((filters as T)[key]) !== JSON.stringify((initialSnapshot as T)[key])) return true
    }
    return false
  })

  return { filters, reset, isActive }
}
