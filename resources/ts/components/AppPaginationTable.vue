<template>
  <v-data-table-server
    :items="items"
    :class="{'app-pagination-table--striped': striped}"
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
      #body
      v-if="errorMsg"
    >
      <div class="d-flex">
        <v-alert
          class="mt-4 mx-auto"
          icon="mdi-close-circle-outline"
          type="error"
        >
          {{ errorMsg }}
        </v-alert>
      </div>
    </template>
    <template
      #loading
      v-if="!$slots.loading"
    >
      <v-skeleton-loader :type="`table-row@${itemsPerPage}`" />
    </template>
  </v-data-table-server>
</template>

<script lang="ts">
import { VDataTableServer } from "vuetify/components/VDataTable"
import { type PropType } from "vue"
import { clone } from "lodash"

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
<script setup lang="ts">
import { ref, watchEffect } from "vue"
import usePaginationData from "@/composables/usePaginationData"

defineOptions({
  inheritAttrs: false
})

const props = defineProps(AppPaginationTableProps)
const items = ref<any[]>([])
const errorMsg = ref<string | undefined>(undefined)
const loading = ref<boolean>(false)

const { pagination, loadData, reload, setPagination } = usePaginationData(props.endpoint, props?.filters, props?.method)

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

defineExpose({
  reload
})

watchEffect(() => {
  console.log("items updated", items.value)
})

async function onChange(options: { itemsPerPage: number, page: number, sortBy: Record<string, any> }) {
  setPagination(options)
  loading.value = true
  const { data, status, error } = await loadData()
  console.log(data)
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
  }
}
</style>
