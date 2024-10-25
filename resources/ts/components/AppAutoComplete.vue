<template>
  <v-text-field
    :loading="true"
    readonly
    v-if="initialLoading"
    v-bind="mergeProps(bindings, {modelValue: ''})"
  />
  <v-autocomplete
    v-else
    ref="standard_ac"
    v-bind="bindings"
    :items="computedItems"
    hide-selected
    return-object
    v-model="objectModel"
    no-filter
    eager
    v-model:search="state.search"
  >
    <template #append-item>
      <slot name="append-item" />
      <div
        class="d-flex justify-center"
        v-intersect="handleLoad"
        v-if="!state.finished"
      >
        <v-progress-circular
          indeterminate
          color="primary"
        />
      </div>
    </template>
    <template
      #append-inner
      v-if="!$slots['append-inner']"
    >
      <v-progress-circular
        indeterminate
        size="12"
        width="2"
        color="primary"
        v-if="loading"
      />
    </template>
    <template
      #no-data
      v-if="loading || $slots['no-data']"
    >
      <slot
        name="no-data"
        v-if="!loading"
      />
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
        name="item"
        v-bind="itemProps"
        v-else
      >
        <v-list-item v-bind="itemProps.props" />
      </slot>
    </template>
    <template
      v-for="(_, name) in $slots"
      #[name]="slotData"
    >
      <slot
        v-if="name !== 'append-item' && name !== 'item'"
        :name="name"
        v-bind="slotData"
      />
    </template>
  </v-autocomplete>
</template>

<script setup lang="ts">
import {computed, mergeProps, nextTick, onMounted, reactive, ref, toValue, useAttrs, watch} from "vue"
import {VAutocomplete} from "vuetify/components/VAutocomplete"
import usePaginationData from "@/composables/usePaginationData"
import {cloneDeep, isEqual} from "lodash"
import {useDebounceFn} from "@vueuse/core"

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
  filters?: Record<string, any>
  groupBy?: string | ((item: any) => string)
} & /* @vue-ignore */ Omit<InstanceType<typeof VAutocomplete>['$props'],
  "loading" | "items" | "ref" | "readonly" | "search" | "modelValue" | "itemValue" | "noFilter" | "multiple" | "returnObject">

type VAutocompleteSlots = InstanceType<typeof VAutocomplete>['$slots']

defineSlots<VAutocompleteSlots>()

const props = withDefaults(defineProps<AppAutoCompleteProps>(), {
  appendItems: () => [],
  prependItems: () => [],
  itemsPerPage: 10,
  itemValue: "id",
  filters: () => ({}),
  groupBy: undefined
})

const model = defineModel<any[] | any>()
const objectModel = defineModel<any[] | any>('object')
const items = ref<any[]>([])
const loading = ref<boolean>(false)

const state = reactive({
  search: "",
  selected: [] as any[],
  finished: false,
})

const filters = computed(() => {
  const filters: { search?: string | null, key: string, selected?: any[] } = {
    search: state.search,
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

const {loadData, pagination, setPagination} = usePaginationData(props.endpoint, filters)

async function reload() {
  setPagination({page: 1})
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
    debounceReload().then()
  } else {
    reload().then()
  }
  oldFilters = cloneDeep(newValue)
}, { deep: true })

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
  const value = props.multiple ? items.value.filter(item => model.value.includes(item[props.itemValue])) : items.value.find(item => item[props.itemValue] == model.value)
  if (isEqual(value, objectModel.value)) {
    return
  }
  objectModel.value = value
}

function setModel() {
  if (objectModel.value === undefined) {
    return
  }

  const value = props.multiple ? objectModel.value.map((item: any) => item[props.itemValue]) : objectModel.value?.[props.itemValue]
  if (isEqual(value, model.value)) {
    return
  }
  model.value = value
}

const initialLoading = ref(true)
onMounted(async () => {
  await reload()
  await nextTick(() => {
    setObjectModel()
    setModel()
    initialLoading.value = false
  })
})
</script>

<style scoped>

</style>
