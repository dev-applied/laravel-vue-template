<template>
  <v-text-field
    v-if="initialLoading"
    :loading="true"
    readonly
    v-bind="mergeProps(bindings, {modelValue: ''})"
  />
  <v-autocomplete
    v-else
    ref="autoComplete"
    v-model="objectModel"
    v-model:search="state.search"
    :items="computedItems"
    eager
    hide-selected
    no-filter
    return-object
    v-bind="bindings"
  >
    <template #prepend-item>
      <slot
        v-if="!loading && !props.allowCustomItems"
        name="prepend-item"
      />
      <v-list-item
        v-else-if="props.allowCustomItems && canSearch && searchItemDoesNotExist && items.length > 0"
        @click="addCustomItem"
      >
        <v-list-item-title>
          No results matching for "<strong>{{ state.search }}</strong>". <kbd>Click</kbd> to create a new one
        </v-list-item-title>
      </v-list-item>
    </template>
    <template #append-item>
      <slot name="append-item" />
      <div
        v-if="!state.finished"
        v-intersect="handleLoad"
        class="d-flex justify-center"
      >
        <v-progress-circular
          color="primary"
          indeterminate
        />
      </div>
    </template>
    <template
      v-if="!$slots['append-inner']"
      #append-inner
    >
      <v-progress-circular
        v-if="loading"
        color="primary"
        indeterminate
        size="12"
        width="2"
      />
    </template>
    <template
      #no-data
    >
      <span v-if="!loading">
        <slot
          v-if="$slots['no-data']"
          name="no-data"
        />
        <v-list-item
          v-else-if="props.allowCustomItems && canSearch"
          @click="addCustomItem"
        >
          <v-list-item-title>
            No results matching for "<strong>{{ state.search }}</strong>". <kbd>Click</kbd> to create a new one
          </v-list-item-title>
        </v-list-item>
        <v-list-item
          v-else-if="!canSearch"
        >
          <v-list-item-title>
            Type at least <strong>{{
              props.minSearchChars
            }}</strong> characters to search {{ props.allowCustomItems ? 'or create a new one' : '' }}
          </v-list-item-title>
        </v-list-item>
        <v-list-item
          v-else
        >
          <v-list-item-title>
            No results found
          </v-list-item-title>
        </v-list-item>
      </span>
    </template>
    <template
      #item="itemProps"
    >
      <v-divider v-if="'divider' in itemProps.item.raw" />
      <v-list-subheader
        v-else-if="'header' in itemProps.item.raw"
        :title="itemProps.item.raw.header"
      />
      <slot
        v-else
        name="item"
        v-bind="itemProps"
      >
        <v-list-item v-bind="itemProps.props" />
      </slot>
    </template>
    <template
      v-for="(_, name) in $slots"
      #[name]="slotData"
    >
      <slot
        v-if="name !== 'append-item' || name !== 'item'"
        :name="name"
        v-bind="slotData"
      />
    </template>
  </v-autocomplete>
</template>

<script lang="ts" setup>
import {computed, mergeProps, nextTick, onMounted, reactive, ref, toRefs, toValue, useAttrs, watch} from "vue"
import {VAutocomplete} from "vuetify/components/VAutocomplete"
import usePaginationData from "@/composables/usePaginationData"
import cloneDeep from 'lodash.clonedeep'
import isEqual from 'lodash.isequal'
import {useDebounceFn} from "@vueuse/core"
import {$http} from "@/plugins/axios"
import {$error} from "@/plugins/errorHandler"

defineOptions({inheritAttrs: false})

export type AppAutoCompleteProps = {
  prependItems?: any[]
  appendItems?: any[]
  mandatory?: boolean
  endpoint: string
  static?: boolean
  itemsPerPage?: number
  itemValue?: string,
  multiple?: boolean,
  itemTitle?: string | ((item: any) => string),
  filters?: Record<string, any>
  groupBy?: string | ((item: any) => string)
  allowCustomItems?: boolean
  minSearchChars?: number
} & /* @vue-ignore */ Omit<InstanceType<typeof VAutocomplete>['$props'],
  "loading" | "items" | "ref" | "readonly" | "search" | "modelValue" | "itemValue" | "noFilter" | "multiple" | "returnObject">

type VAutocompleteSlots = InstanceType<typeof VAutocomplete>['$slots']

defineSlots<VAutocompleteSlots>()

const props = withDefaults(defineProps<AppAutoCompleteProps>(), {
  appendItems: () => [],
  prependItems: () => [],
  itemsPerPage: 10,
  itemValue: "id",
  itemTitle: undefined,
  filters: () => ({}),
  groupBy: undefined,
  allowCustomItems: false,
  minSearchChars: 0,
})

const model = defineModel<any[] | any>()
const objectModel = defineModel<any[] | any>('object')
const items = ref<any[]>([])
const loading = ref<boolean>(false)
const searchItemDoesNotExist = computed(() => {
  return !items.value.some(item => item[props.itemTitle] === state.search)
})

const state = reactive({
  search: "",
  selected: [] as any[],
  finished: false,
})


const getSearch = () => {
  if (props.itemTitle) {
    if (objectModel.value?.[props.itemTitle] === state.search) {
      return ""
    }
  }
  return state.search
}

const canSearch = computed(() => {
  return state.search?.length >= props.minSearchChars || model.value
})

const filters = computed(() => {
  const search = getSearch()
  const filters: { search?: string | null, key: string, selected?: any[] } = {
    search: canSearch.value ? search : null,
    key: props.itemValue as string || "id",
  }

  if (props.filters) {
    Object.assign(filters, props.filters)
  }

  if (model.value !== undefined) {
    filters.selected = Array.isArray(model.value) ? model.value : [model.value]
  } else if (objectModel.value !== undefined) {
    filters.selected = Array.isArray(objectModel.value) ? objectModel.value.map(i => i[props.itemValue]) : [objectModel.value?.[props.itemValue]]
  }

  return filters
})

const bindings = computed(() => {
  return mergeProps(props, useAttrs())
})

const computedItems = computed(() => {
  let computedItems = [...props.prependItems, ...items.value, ...props.appendItems]

  if (props.groupBy) {
    computedItems.forEach(item => {
      item._app_autocomplete_grouping = typeof props.groupBy === 'function' ? props.groupBy(item) : item[props.groupBy]
    })

    computedItems = computedItems.reduce((acc, item) => {
      if (!acc.length || acc[acc.length - 1]._app_autocomplete_grouping !== item._app_autocomplete_grouping) {
        acc.push({header: item._app_autocomplete_grouping, _app_autocomplete_grouping: item._app_autocomplete_grouping})
      }
      acc.push(item)
      return acc
    }, [])
  }

  return computedItems
})
const {endpoint} = toRefs(props)
const {loadData, pagination, setPagination} = usePaginationData(endpoint, filters)

async function reload() {
  setPagination({page: 1})
  if (!canSearch.value) {
    items.value = []
    loading.value = false
    return
  }

  const {data, status} = await loadData()

  switch (status) {
    case "canceled":
      return
    case "empty":
    case "error":
      items.value = data
      await nextTick(() => {
        state.finished = true
      })
      break
    default:
      items.value = data
      await nextTick(() => {
        state.finished = false
      })
  }
  loading.value = false
}

watch(() => props.disabled, () => {
  if (!props.disabled) {
    reload()
  }
}, {immediate: true})

const debounceReload = useDebounceFn(() => {
  reload().then()
}, 1000)

let oldFilters = cloneDeep(toValue(filters))
watch(() => filters, (newValue: any) => {
  newValue = toValue(newValue)

  if (JSON.stringify(newValue) === JSON.stringify(oldFilters)) {
    return
  }
  if (newValue?.search !== oldFilters?.search) {
    loading.value = true
    debounceReload().then()
  } else {
    reload().then()
  }
  oldFilters = cloneDeep(newValue)
}, {deep: true})

watch(() => props.endpoint, () => {
  reload()
})

watch(() => props.itemsPerPage, () => {
  setPagination({itemsPerPage: props.itemsPerPage})
}, {immediate: true})

watch(() => model.value, setObjectModel)

watch(() => objectModel.value, setModel)

async function handleLoad(_isIntersecting: boolean, entries: IntersectionObserverEntry[]) {
  if (entries[0].intersectionRatio <= 0) return
  if (!canSearch.value) {
    state.finished = true
    items.value = []
    loading.value = false
    return
  }
  if (state.finished) return

  loading.value = true
  setPagination({page: pagination.value.page + 1})
  const {data, status} = await loadData()
  loading.value = false
  switch (status) {
    case "canceled":
      return
    case "empty":
    case "error":
      state.finished = true
      break
    default:
      break
  }
  items.value = items.value.concat(data)
}

function setObjectModel() {
  if (model.value === undefined) {
    return
  }
  const value = props.multiple ? items.value.filter(item => model.value.includes(item[props.itemValue])) : (items.value.find(item => item[props.itemValue] == model.value) || null)
  if (isEqual(value, objectModel.value)) {
    return
  }
  objectModel.value = value
}

function setModel() {
  if (objectModel.value === undefined) {
    return
  }

  const value = props.multiple ? objectModel.value.map((item: any) => (item[props.itemValue] || null)) : (objectModel.value?.[props.itemValue] || null)
  if (isEqual(value, model.value)) {
    return
  }
  model.value = value
}

const initialLoading = ref(true)
onMounted(async () => {
  if (canSearch.value) {
    await reload()
    await nextTick(() => {
      setObjectModel()
      nextTick(() => {
        setModel()
      })
      initialLoading.value = false
    })
  } else {
    loading.value = false
    initialLoading.value = false
    return
  }
})

const autoComplete = ref<InstanceType<typeof VAutocomplete>>()

async function addCustomItem() {
  const {
    data: {newItem, message, errors},
    status
  } = await $http.post(props.endpoint, {name: state.search}).catch((e: any) => e)
  if ($error(status, message, errors)) return
  items.value.push(newItem)
  if (props.multiple) {
    objectModel.value.push(newItem)
  } else {
    objectModel.value = newItem
  }
  if (autoComplete.value) {
    autoComplete.value.$el?.getElementsByTagName('input')?.[0].blur()
  }
}
</script>
