<template>
  <app-pagination-table
    v-if="mdAndUp"
    :hide-default-footer="static"
    v-bind="TableProps"
    ref="table"
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
  </app-pagination-table>
  <app-list-table
    v-else
    ref="list"
    v-bind="ListProps"
  >
    <template #item="{ item }">
      <slot
        name="mobile-item"
        :item="item"
      />
    </template>
    <template #no-items>
      <slot name="no-items" />
    </template>
  </app-list-table>
</template>

<script setup lang="ts">
import { computed, ref, useAttrs } from "vue"
import AppPaginationTable, {AppPaginationTableProps} from "@/components/AppPaginationTable.vue"
import AppListTable, {AppListTableProps} from "@/components/AppListTable.vue"
import {useDisplay} from "vuetify"
import { pick } from "lodash"

const props = defineProps({
  ...AppPaginationTableProps,
  ...AppListTableProps
})

const attrs = useAttrs()
const TableProps = computed(() => pick({...props,  attrs}, Object.keys(AppPaginationTableProps)))
const ListProps = computed(() => pick({...props,  attrs}, Object.keys(AppListTableProps)))


const table = ref<InstanceType<typeof AppPaginationTable> | null>(null)
const list = ref<InstanceType<typeof AppListTable> | null>(null)
const {mdAndUp} = useDisplay()

function reload(resetPage = true) {
  if (mdAndUp.value) {
    return table.value?.reload(resetPage)
  } else {
    return list.value?.reload(resetPage)
  }
}

defineExpose({
  reload
})
</script>

<style scoped>

</style>
