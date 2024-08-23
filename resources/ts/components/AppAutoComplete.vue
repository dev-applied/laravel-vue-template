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
    :items="items"
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
      #selection="{item}"
      v-if="!$slots['selection']"
    >
      {{ item }}
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
  itemValue?: string
  filters?: Record<string, any>
} & /* @vue-ignore */ Omit<InstanceType<
  typeof VAutocomplete>['$props'],
  "loading" | "items" | "ref" | "readonly" | "search" | "modelValue" | "no-filter" | "itemValue" | "noFilter"
>

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

const state = reactive({
  search: "",
  selected: [] as any[],
  finished: false,
  searching: false
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
    let value = Array.isArray(model.value) ? model.value : [model.value]
    filters.selected = value.map(item => {
      if (!props.returnObject) return item
      return item[filters.key]
    })
  }

  return filters
})

const attrs = useAttrs()
const bindings = computed(() => {
  return mergeProps(props, attrs)
})

function done(type: "empty" | "error" | "ok") {
  if (model.value !== undefined) {
    objectModel.value = items.value.find(item => item[props.itemValue] == model.value)
  }
  switch (type) {
    case "empty":
      state.finished = true
      break
    case "error":
      state.finished = true
      break
    case "ok":
      state.finished = false
      break
  }
}

const {loading, loadData, currentPage, reload, items} = usePaginationData(props.endpoint, filters, 'GET', true, done)


watch(() => props.disabled, () => {
  if (!props.disabled) {
    state.finished = false
    reload(true, done)
  }
}, {immediate: true})

async function handleLoad(isIntersecting: boolean, entries) {
  if (entries[0].intersectionRatio <= 0) return
  if (state.finished) return
  const page = currentPage.value + 1
  await loadData({page, itemsPerPage: props.itemsPerPage, done})
}


const initialLoading = ref(true)
onMounted(async () => {
  await reload(true, done)
  initialLoading.value = false
})


</script>

<style scoped>

</style>
