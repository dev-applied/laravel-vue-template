import { computed, ref, type ComputedRef, type Ref } from "vue"

export interface UseSelectionReturn<K> {
  /** Reactive Set of selected keys. */
  selectedKeys: Ref<Set<K>>
  /** Reactive count for UX ("3 selected"). */
  count:        ComputedRef<number>
  /** Whether ALL items in the current page are selected. */
  allSelected:  (items: K[]) => boolean
  /** Whether SOME items are selected (for the indeterminate checkbox state). */
  someSelected: (items: K[]) => boolean
  isSelected:   (key: K) => boolean
  toggle:       (key: K) => void
  /** Select every key from `items`. Does NOT clear existing selections. */
  selectAll:    (items: K[]) => void
  /** Deselect every key from `items`. */
  deselectAll:  (items: K[]) => void
  /** Toggle between all-selected and none for `items`. */
  toggleAll:    (items: K[]) => void
  clear:        () => void
}

/**
 * Multi-select state for table rows. Generic over the row key type (number or string).
 *
 *   const { selectedKeys, count, isSelected, toggle, toggleAll, clear } =
 *     useSelection<number>()
 *
 *   // template:
 *   <v-checkbox :model-value="isSelected(item.id)" @click.stop="toggle(item.id)" />
 *   <v-btn v-if="count > 0" color="error" @click="bulkDelete">Delete {{ count }}</v-btn>
 *
 * Pairs with AppPaginationTable: pass `selectedKeys.value` (or `Array.from(selectedKeys.value)`)
 * to your bulk-action handlers. AppPaginationTable doesn't dictate selection model so the
 * composable owns it cleanly.
 */
export function useSelection<K = number>(): UseSelectionReturn<K> {
  const selectedKeys = ref<Set<K>>(new Set()) as Ref<Set<K>>

  const count = computed(() => selectedKeys.value.size)

  function isSelected(key: K): boolean {
    return selectedKeys.value.has(key)
  }

  function toggle(key: K) {
    const next = new Set(selectedKeys.value)
    if (next.has(key)) next.delete(key)
    else next.add(key)
    selectedKeys.value = next
  }

  function selectAll(items: K[]) {
    const next = new Set(selectedKeys.value)
    for (const k of items) next.add(k)
    selectedKeys.value = next
  }

  function deselectAll(items: K[]) {
    const next = new Set(selectedKeys.value)
    for (const k of items) next.delete(k)
    selectedKeys.value = next
  }

  function toggleAll(items: K[]) {
    if (allSelected(items)) deselectAll(items)
    else selectAll(items)
  }

  function allSelected(items: K[]): boolean {
    if (!items.length) return false
    return items.every(k => selectedKeys.value.has(k))
  }

  function someSelected(items: K[]): boolean {
    if (!items.length) return false
    return items.some(k => selectedKeys.value.has(k))
  }

  function clear() {
    selectedKeys.value = new Set()
  }

  return {
    selectedKeys,
    count,
    allSelected,
    someSelected,
    isSelected,
    toggle,
    selectAll,
    deselectAll,
    toggleAll,
    clear,
  }
}
