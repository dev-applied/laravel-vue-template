import {computed, type MaybeRef, nextTick, toValue, watch} from "vue"
import type {AutocompleteItem, PrimitiveId,} from "./types"
import {ensureArray, extractId, isEqualId} from "./utils"
import {useProxiedModel} from "@/components/fields/AppAutoComplete/useProxiedModel"

interface UseModelSyncOptions<_T = any> {
  itemValue: string;
  multiple: boolean;
  items: MaybeRef<AutocompleteItem<_T>[]>;
}

export function useModelSync<T = any>(
  props: any,
  options: UseModelSyncOptions<T>
) {
  const { itemValue, multiple, items } = options

  // External values (from parent)
  const modelValue = useProxiedModel(props, 'modelValue', undefined)
  const object = useProxiedModel(props, 'object', undefined)

  // Prevent circular updates
  let updatingFromModel = false
  let updatingFromObjectModel = false

  // ------------------------------------------------------------
  // Helpers
  // ------------------------------------------------------------
  const selectedIds = computed<PrimitiveId[]>(() => {
    return ensureArray(modelValue.value) as PrimitiveId[]
  })

  const selectedObjects = computed<AutocompleteItem<T>[]>(() => {
    return ensureArray(object.value) as AutocompleteItem<T>[]
  })

  function syncFromModelValue() {
    if (updatingFromObjectModel) {
      // console.log('skipping syncFromModelValue to avoid loop')
      return
    }

    updatingFromModel = true

    const ids = selectedIds.value
    const matched = toValue(items).filter((item) =>
      ids.some((id) => isEqualId(extractId(item, itemValue), id))
    )

    // console.trace('syncFromModelValue', ids, matched)

    // Ensure object stays in sync
    object.value = multiple ? matched : (matched[0] ?? null)

    nextTick(() => {
      updatingFromModel = false
    })
  }

  function syncFromObjectModel() {
    if (updatingFromModel) {
      // console.log('skipping syncFromObjectModel to avoid loop')
      return
    }

    updatingFromObjectModel = true

    const ids = selectedObjects.value.map((obj) => extractId(obj, itemValue))
    // console.trace("syncFromObjectModel", ids)

    modelValue.value =  multiple ? ids : (ids[0] ?? null)

    nextTick(() => {
      updatingFromObjectModel = false
    })
  }

  // ------------------------------------------------------------
  // Watchers
  // ------------------------------------------------------------
  watch(modelValue, syncFromModelValue)
  watch(object, syncFromObjectModel)

  return {
    modelValue,
    object,

    selectedIds,
    selectedObjects,
    syncFromModelValue,
    syncFromObjectModel,
  }
}
