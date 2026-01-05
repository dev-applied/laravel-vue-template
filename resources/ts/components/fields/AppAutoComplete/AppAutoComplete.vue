<template>
  <div class="text-left">
    <div
      v-if="labelPosition === 'outside' && label"
      class="mb-2"
    >
      {{ label }}<span
        v-if="required"
        class="text-error"
      >*</span>
    </div>
    <VTextField
      v-if="initialLoading"
      v-bind="mergedProps"
      :model-value="''"
      readonly
      loading
    >
      <template
        v-if="labelPosition === 'inside'"
        #label
      >
        {{ label }}<span
          v-if="required"
          class="text-error"
        >*</span>
      </template>
      <template #prepend-inner>
        <slot name="prepend-inner" />
      </template>
    </VTextField>

    <VAutocomplete
      v-else
      v-bind="mergedProps"
      v-model="modelValue"
      no-filter
      :items="finalItems"
      :loading="searchLoading"
      hide-selected
      :chips="multiple"
      :multiple="multiple"
      :item-value="itemValue"
      :item-title="computedItemTitle"
      :return-object="false"
      :hide-no-data="hideNoData"
      :mandatory="mandatory"
      :error-messages="errorMessages"
      @update:search="handleSearchUpdate"
      @update:menu="handleMenuChange"
    >
      <template
        v-if="labelPosition === 'inside'"
        #label
      >
        {{ label }}<span
          v-if="required"
          class="text-error"
        >*</span>
      </template>

      <template #prepend-item>
        <slot name="prepend-item" />
      </template>

      <template
        v-for="slotName in filteredSlotNames"
        #[slotName]="slotProps"
      >
        <slot
          :name="slotName"
          v-bind="slotProps || {}"
        />
      </template>

      <template #item="{ props: newProps, item }">
        <VListSubheader
          v-if="item?.raw?.type === 'subheader'"
          class="font-weight-bold"
          sticky
        >
          {{ item.raw.header }}
        </VListSubheader>

        <slot
          v-else-if="$slots.item"
          name="item"
          v-bind="{ newProps, item }"
        />

        <VListItem
          v-else
          v-bind="newProps"
          :title="item.title"
        />
      </template>

      <template #no-data>
        <div
          v-if="searchLoading"
          class="pa-3"
        >
          <VSkeletonLoader type="list-item-avatar-two-line" />
        </div>
        <slot
          v-else
          name="no-data"
          :search="search"
        >
          <VListItem
            key="no-data"
            :title="computedNoDataText"
          />
        </slot>
      </template>

      <template #append-item>
        <VListItem
          v-if="allowCustomItems && canCreateCustomItem"
          prepend-icon="mdi-plus"
          @click="createItem"
        >
          <VListItemTitle>Create “{{ search }}”</VListItemTitle>
        </VListItem>

        <div
          v-intersect="onIntersect"
        >
          <VSkeletonLoader
            v-if="pagination.loading.value"
            type="list-item-avatar-two-line"
          />
          <div
            v-else
            style="height: 1px;"
          />
        </div>
      </template>
    </VAutocomplete>
  </div>
</template>

<script setup lang="ts">
import {computed, nextTick, onMounted, type PropType, ref, toValue, useAttrs, useSlots, watch} from "vue"
import {VAutocomplete, VListItem, VListItemTitle, VListSubheader, VSkeletonLoader, VTextField} from "vuetify/components"
import {$http} from "@/plugins/axios"
import cloneDeep from "lodash.clonedeep"
import isEqual from "lodash.isequal"
import omit from "lodash.omit"

import type {AutocompleteItem, PrimitiveId} from "./types"
import {makeUseRemoteSearchProps, useRemoteSearch} from "./useRemoteSearch"
import {useModelSync} from "./useModelSync"
import {ensureArray, extractId, extractTitle, groupItems} from "./utils"
import {useCreateItem} from './useCreateItem'

const props = defineProps({
  itemValue: {type: String, default: "id"},
  itemTitle: {type: [String, Function] as PropType<any>, default: "name"},
  modelValue: {type: [String, Number, Array] as PropType<PrimitiveId | PrimitiveId[] | null>, default: null},
  object: {type: [Object, Array] as PropType<AutocompleteItem | AutocompleteItem[] | null>, default: null},
  multiple: {type: Boolean, default: false},
  prependItems: {type: Array as PropType<AutocompleteItem[]>, default: () => []},
  appendItems: {type: Array as PropType<AutocompleteItem[]>, default: () => []},
  groupBy: {type: [String, Function] as PropType<any>, default: undefined},
  allowCustomItems: {type: Boolean, default: false},
  required: {type: Boolean, default: false},
  label: {type: String, default: undefined},
  newItemKey: {type: String, default: "newItem"},
  excludeIds: {type: Array as PropType<PrimitiveId[]>, default: () => []},
  disabled: {type: Boolean, default: false},
  readonly: {type: Boolean, default: false},
  mandatory: {type: Boolean, default: false},
  errorMessages: {type: [String, Array] as PropType<string | readonly string[]>, default: () => []},
  static: {type: Boolean, default: false},
  noDataText: {type: String, default: "No data available."},
  labelPosition: {type: String as PropType<'inside' | 'outside'>, default: 'inside'},
  ...makeUseRemoteSearchProps()
})


const attrs = useAttrs()

const slots = useSlots()
const filteredSlotNames = computed(() => {
  return Object.keys(slots).filter(name => !['item', 'append-item', 'no-data'].includes(name))
})



const combinedFilters = computed(() => {
  const f: Record<string, any> = {...props.filters}
  f.search = search.value
  f.key = props.itemValue
  f.excludeIds = props.excludeIds
  if (modelValue.value) {
    f.selected = ensureArray(modelValue.value)
  } else {
    f.selected = ensureArray(object.value).map(o => extractId(o, props.itemValue))
  }
  return f
})

const {
  items: searchResults,
  initialLoading: searchInitialLoading,
  searchLoading,
  reload,
  // clear,
  loadNextPage,
  pagination,
} = useRemoteSearch({
  endpoint: props.endpoint!,
  itemsPerPage: props.static ? -1 : 10,
  filters: combinedFilters,
  // minSearchChars: props.minSearchChars,
  $http,
})

/* --------------------------------------------------------------------------
 * Computed Items (Grouped + Hydrated)
 * -------------------------------------------------------------------------- */
const finalItems = computed(() => {
  // 1. Merge all sources
  const list = [...props.prependItems, ...searchResults.value, ...props.appendItems]

  if (!props.groupBy) {
    return list
  }

  const groups = groupItems(list, props.groupBy)
  const titleKey = typeof props.itemTitle === 'string' ? props.itemTitle : 'name'

  return groups.flatMap(g => {
    const headerItem = {
      header: g.header,
      type: 'subheader',
      [titleKey]: g.header,
      [props.itemValue]: `header-${g.header}`
    }
    return [headerItem, ...g.items]
  })
})

/* --------------------------------------------------------------------------
 * Models & Sync
 * -------------------------------------------------------------------------- */
const {modelValue, object, selectedObjects, syncFromModelValue, syncFromObjectModel} = useModelSync(props, {
  itemValue: props.itemValue,
  // @ts-ignore
  items: finalItems,
  multiple: props.multiple,
})

/* --------------------------------------------------------------------------
 * Search Logic
 * -------------------------------------------------------------------------- */

const search = ref("")

function handleSearchUpdate(newSearch: string) {
  if (search.value === newSearch) {
    return
  }

  // Need to wait until next tick to wait the model to be set
  nextTick(() => {
    if (!props.multiple && selectedObjects.value.length === 1) {
      const title = extractTitle(selectedObjects.value[0], props.itemTitle)
      if (newSearch === title) {
        search.value = ""
        return
      }
    }
    search.value = newSearch
  })
}


/* --------------------------------------------------------------------------
 * End Search Logic
 * -------------------------------------------------------------------------- */


// Setup create item composable
const {create} = useCreateItem({endpoint: props.endpoint!, newItemKey: props.newItemKey, $http})

/* --------------------------------------------------------------------------
 * Watcher Logic
 * -------------------------------------------------------------------------- */
let debounceTimer: ReturnType<typeof setTimeout> | null = null
let oldFilters = cloneDeep(toValue(combinedFilters))

watch(combinedFilters, (newFilters) => {
  const filtersChanged = !isEqual(newFilters, oldFilters)

  if (!filtersChanged) return

  const searchChanged = newFilters?.search !== oldFilters?.search

  const selectedIdsChanged = !isEqual(newFilters?.selected, oldFilters?.selected)

  // Update history immediately
  oldFilters = cloneDeep(newFilters)

  if (selectedIdsChanged) {
    if (newFilters?.selected.every((id: PrimitiveId) => searchResults.value.map(s => extractId(s, props.itemValue)).includes(id))) {
      // All selected IDs are already in the current results, no need to reload
      return
    }
  }

  if (searchChanged) {
    // const s = String(newFilters.search || "").trim()

    // // STRICT CHECK:
    // // If minSearchChars is set, we must strictly block searches below that limit.
    // // EXCEPTION: If we have selected items, always allow the query
    // if (props.minSearchChars > 0 && s.length < props.minSearchChars) {
    //   const selected = newFilters?.selected || []
    //   if (!Array.isArray(selected) || selected.length === 0) {
    //     if (debounceTimer) {
    //       clearTimeout(debounceTimer)
    //       debounceTimer = null
    //     }
    //     clear()
    //     return
    //   }
    // }

    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      reload()
    }, 1000)
    return
  }

  // Other filters changed
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }
  reload()
}, {deep: true})

/* --------------------------------------------------------------------------
 * Props & Helpers
 * -------------------------------------------------------------------------- */
const computedItemTitle = computed<any>(() => props.itemTitle)
const hideNoData = computed(() => searchInitialLoading.value || searchLoading.value)

const computedNoDataText = computed(() => {
  // If minSearchChars is set and we're below the minimum
  // if (props.minSearchChars > 0) {
  //   const searchTerm = String(search.value || "").trim()
  //   const hasSelection = selectedObjects.value.length > 0
  //
  //   // If below minimum and no selection, show the "enter X characters" message
  //   if (searchTerm.length < props.minSearchChars && !hasSelection) {
  //     return `Please enter ${props.minSearchChars} characters to search`
  //   }
  // }

  return props.noDataText
})

const autocompleteProps = computed(() => ({
  disabled: props.disabled,
  readonly: props.readonly,
  errorMessages: props.errorMessages,
}))
const mergedProps = computed(() => (omit({...attrs, ...autocompleteProps.value}, ['label'])))

const canCreateCustomItem = computed(() => {
  if (!props.allowCustomItems) return false
  if (!search.value) return false
  if (searchInitialLoading.value || searchLoading.value) return false

  return !searchResults.value.some(
    (x) => {
      const title = extractTitle(x, props.itemTitle)
      return String(title).toLowerCase() === search.value.toLowerCase()
    }
  )
})

/* --------------------------------------------------------------------------
 * Lifecycle & Infinite Scroll
 * -------------------------------------------------------------------------- */
const isIntersecting = ref(false)

function onIntersect(isIntersectingVal: boolean) {
  isIntersecting.value = isIntersectingVal

  if (!isIntersectingVal) return
  if (pagination.loading.value || pagination.finished.value) return
  loadNextPage()
}

// Watch for when loading finishes while still intersecting
watch(
  () => pagination.loading.value,
  (isLoading) => {
    if (!isLoading && isIntersecting.value && !pagination.finished.value) {
      // Small delay to let DOM settle before triggering another load
      setTimeout(() => {
        if (isIntersecting.value && !pagination.loading.value && !pagination.finished.value) {
          loadNextPage()
        }
      }, 100)
    }
  }
)

// Needed to reset intersection when menu closes
function handleMenuChange(menu: boolean) {
  if (!menu) {
    isIntersecting.value = false
  }
}


const emit = defineEmits(['update:modelValue', 'update:object'])

async function createItem() {
  // Delegate to reusable composable
  const name = search.value
  const {item: newItem, error} = await create(name)
  if (error || !newItem) {
    // preserve previous behavior: clear search on error and return
    search.value = ''
    return
  }

  searchResults.value.push(newItem)

  search.value = extractTitle(newItem, props.itemTitle)
  const newId = extractId(newItem, props.itemValue)

  if (props.multiple) {
    const currentIds = ensureArray(props.modelValue)
    emit('update:modelValue', [...currentIds, newId])
  } else {
    emit('update:modelValue', newId)
  }

  if (document.activeElement instanceof HTMLElement) {
    document.activeElement.blur()
  }
}

const initialLoading = ref(true)

onMounted(async () => {
  // Perform the SINGLE initial search
  await reload()
  if (modelValue.value) {
    syncFromModelValue()
  } else if (object.value) {
    syncFromObjectModel()
  }
  initialLoading.value = false
})
</script>

<style scoped>
</style>
