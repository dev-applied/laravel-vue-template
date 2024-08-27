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
    v-model="model"
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
      v-for="(_, name) in $slots"
      #[name]="slotData"
    >
      <slot
        v-if="name !== 'append-item'"
        :name="name"
        v-bind="slotData"
      />
    </template>
  </v-autocomplete>
</template>

<script setup lang="ts">
import {computed, mergeProps, onMounted, reactive, ref, useAttrs, watch} from "vue"
import {VAutocomplete} from "vuetify/components/VAutocomplete"
import usePaginationData from "@/composables/usePaginationData"

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
    key: props.itemValue as string || "id"
  }

  if (props.filters) {
    Object.assign(filters, props.filters)
  }

  if (model.value !== undefined) {
    filters.selected = Array.isArray(model.value) ? model.value : [model.value]
  }

  return filters
})

const bindings = computed(() => {
  return mergeProps(props, useAttrs())
})

const computedItems = computed(() => {
  return [...props.prependItems, ...items.value, ...props.appendItems]
})

const {loadData, reload, pagination, setPagination} = usePaginationData(props.endpoint, filters)

watch(() => props.disabled, () => {
  if (!props.disabled) {
    state.finished = false
    reload()
  }
}, {immediate: true})

watch(() => items.value, () => {
  if (model.value === undefined) {
    return
  }
  if (props.multiple) {
    objectModel.value = items.value.filter(item => model.value.includes(item[props.itemValue]))
  } else {
    objectModel.value = items.value.find(item => item[props.itemValue] == model.value)
  }
}, {immediate: true})

watch(() => filters.value, () => {
  state.finished = false
}, {deep: true})

async function handleLoad(_isIntersecting: boolean, entries: IntersectionObserverEntry[]) {
  if (entries[0].intersectionRatio <= 0) return
  if (state.finished) return

  loading.value = true
  const {data, status} = await loadData()
  loading.value = false
  setPagination({page: pagination.value.page + 1})
  if (status === "error" || status === 'empty') {
    state.finished = true
    return
  }
  items.value.concat(data)
}

const initialLoading = ref(true)
onMounted(async () => {
  await reload()
  initialLoading.value = false
})


</script>

<style scoped>

</style>
