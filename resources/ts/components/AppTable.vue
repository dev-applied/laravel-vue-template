<template>
  <pagination-table
    v-if="$vuetify.display.mdAndUp"
    v-bind="{...$attrs, ...$props}"
    ref="table"
  >
    <template v-for="(_, name) in $slots" v-slot:[name]="slotData">
      <slot :name="name" v-bind="slotData" />
    </template>
  </pagination-table>
  <list-table
    v-else
    ref="list"
    v-bind="{...$attrs, ...$props}"
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
  </list-table>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue"
import type { StoreDefinition } from "pinia"
import PaginationTable from "@/components/AppPaginationTable.vue"
import ListTable from "@/components/AppListTable.vue"



export default defineComponent({
  components: { ListTable, PaginationTable },
  expose: ["reload"],
  inheritAttrs: false,
  props: {
    endpoint: {
      type: String,
      required: true
    },
    method: {
      type: String,
      default: "GET",
      validator(value: "POST" | "GET" | "PUT" | "PATCH") {
        // The value must match one of these strings
        return ["POST", "GET", "PUT", "PATCH"].includes(value)
      },
    },
    additionalRequestData: {
      type: Object,
      required: false,
      default: () => {
      }
    },
    enableSearch: {
      type: Boolean,
      default: false
    },
    store: {
      type: Function as PropType<StoreDefinition>,
      default: null
    },
    storeFieldName: {
      type: String,
      default: null
    },
    storePaginationName: {
      type: String,
      default: "pagination"
    },
    search: {
      type: String,
      default: ""
    },
    fieldName: {
      type: String,
      default: null
    },
    enableFilter: {
      type: Boolean,
      default: false
    },
    storeModule: {
      type: Function as PropType<StoreDefinition>,
      default: null
    },
    threeLine: {
      type: Boolean,
      default: false
    },
    scrollable: {
      type: Boolean,
      default: false
    },
    height: {
      type: [String, Number],
      default: "100%"
    },
  },
  methods: {
    reload(resetPage = true) {
      if (this.$vuetify.display.mdAndUp) {
        return (this.$refs.table as Vuetify.PaginationTable).reload(resetPage)
      } else {
        return (this.$refs.list as Vuetify.ListTable).reload(resetPage)
      }
    }
  },
})
</script>

<style scoped>

</style>
