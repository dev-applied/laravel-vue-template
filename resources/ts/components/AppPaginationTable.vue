<template>
  <v-data-table-server
    :class="{'app-pagination-table--striped': striped}"
    :items="items"
    :items-length="pagination.total"
    :loading="loading"
    v-bind="{...$props, ...$attrs}"
    @update:options="onChange"
  >
    <template
      v-for="(_, name) in $slots"
      #[name]="slotData"
    >
      <slot
        :name="name"
        v-bind="slotData"
      />
    </template>
    <template
      v-if="errorMsg"
      #body
    >
      <div class="d-flex">
        <v-alert
          class="mt-4 mx-auto"
          icon="cancel"
          type="error"
        >
          {{ errorMsg }}
        </v-alert>
      </div>
    </template>
    <template
      v-if="!$slots.loading"
      #loading
    >
      <v-skeleton-loader :type="`table-row@${itemsPerPage}`" />
    </template>
  </v-data-table-server>
</template>

<script lang="ts">
import {VDataTableServer} from "vuetify/components/VDataTable"
import {type PropType} from "vue"
import {clone} from "lodash"

const VDataTableServerProps = clone(VDataTableServer.props)
delete VDataTableServerProps.itemsLength
delete VDataTableServerProps.items
delete VDataTableServerProps.loading
delete VDataTableServerProps.class
delete VDataTableServerProps._as


export const AppPaginationTableProps = {
  itemsPerPage: {
    type: Number,
    default: 10
  },
  itemsPerPageOptions: {
    type: Array as PropType<number[]>,
    default: () => [10, 25, 50, 100, 200]
  },
  endpoint: {
    type: String as PropType<string>,
    required: true,
    default: ""
  },
  filters: {
    type: Object as PropType<Record<string, any>>,
    default: () => ({})
  },
  method: {
    type: String as PropType<"POST" | "GET" | "PUT" | "PATCH">,
    default: "GET"
  },
  striped: {
    type: Boolean,
    default: false
  },
  static: {
    type: Boolean,
    default: false
  }
}
</script>
<script lang="ts" setup>
import {ref, toValue, watch, watchEffect} from "vue"
import usePaginationData from "@/composables/usePaginationData"
import {cloneDeep} from "lodash"
import {useDebounceFn} from "@vueuse/core"

defineOptions({
  inheritAttrs: false
})

const props = defineProps(AppPaginationTableProps)
const items = ref<any[]>([])
const errorMsg = ref<string | undefined>(undefined)
const loading = ref<boolean>(false)
const {endpoint, filters, method} = toRefs(props)

const {pagination, loadData, setPagination} = usePaginationData(endpoint, filters, method)

watchEffect(() => {
  if (props.static) {
    setPagination({
      itemsPerPage: -1
    })
  } else {
    setPagination({
      itemsPerPage: props.itemsPerPage
    })
  }
})

const debounceReload = useDebounceFn(() => {
  reload().then()
}, 1000)

let oldFilters = cloneDeep(toValue(props?.filters))
watch(() => props?.filters, (newValue: any) => {
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
}, {deep: true})

async function reload() {
  setPagination({page: 1})
  loading.value = true
  const {data, status, error} = await loadData()
  loading.value = false
  if (status === "canceled") {
    return
  }
  errorMsg.value = error
  items.value = data
}

defineExpose({
  reload
})

async function onChange(options: { itemsPerPage: number, page: number, sortBy: Record<string, any> }) {
  setPagination(options)
  loading.value = true
  const {data, status, error} = await loadData()
  loading.value = false
  if (status === "canceled") {
    return
  }
  errorMsg.value = error
  items.value = data
}
</script>

<style lang="scss" scoped>
.app-pagination-table {
  &--striped {
    :deep(tbody tr:nth-of-type(odd)) {
      background-color: rgba(0, 0, 0, .05);
    }

    :deep(tbody tr) {
      &:hover {
        background-color: rgba(0, 0, 0, .1);
      }
    }
  }
}
</style>
