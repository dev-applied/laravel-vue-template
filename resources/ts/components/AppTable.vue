<template>
  <app-pagination-table
    v-if="mdAndUp"
    ref="table"
    :hide-default-footer="static"
    v-bind="TableProps"
  >
    <template
      v-for="(_, name) in $slots"
      #[name]="slotData"
    >
      <!-- `slotData || {}` rather than `slotData`: a zero-argument slot
           (Vuetify's no-data, loading, top, bottom, ...) forwards `undefined`,
           and Vue's guardReactiveProps turns that into null. renderSlot only
           defaults props for `undefined`, so null reaches `props.key` and
           throws — blanking the entire page, not just the slot. -->
      <slot
        :name="name"
        v-bind="slotData || {}"
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
        :item="item"
        name="mobile-item"
      />
    </template>
    <template #no-items>
      <slot name="no-items" />
    </template>
  </app-list-table>
</template>

<script lang="ts" setup>
import {computed, ref, useAttrs} from "vue"
import AppPaginationTable, {AppPaginationTableProps} from "@/components/AppPaginationTable.vue"
import AppListTable, {AppListTableProps} from "@/components/AppListTable.vue"
import {useDisplay} from "vuetify"

// Tiny replacement for lodash.pick (which has an unpatched prototype-pollution
// CVE-2020-8203 and no upstream fix). Returns a new object with only `keys`
// that exist on `src`.
function pick<T extends Record<string, unknown>>(src: T, keys: readonly (string | number | symbol)[]): Partial<T> {
  const out: Partial<T> = {}
  for (const k of keys) {
    if (k in src) (out as Record<string | number | symbol, unknown>)[k] = src[k as keyof T]
  }
  return out
}

const props = defineProps({
  ...AppPaginationTableProps,
  ...AppListTableProps
})

const attrs = useAttrs()
const TableProps = computed(() => pick({...props, attrs}, Object.keys(AppPaginationTableProps)))
const ListProps = computed(() => pick({...props, attrs}, Object.keys(AppListTableProps)))


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
