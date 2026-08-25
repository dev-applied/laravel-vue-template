<template>
  <div class="list-table">
    <v-toolbar
      v-show="showSearchBar || showFilterBar"
      :flat="smAndDown"
      class="list-table__header"
      color="white"
      height="40"
    >
      <v-text-field
        v-if="showSearchBar"
        v-model="search"
        :readonly="loading"
        class="list-table__header__search"
        density="compact"
        hide-details
        label="Search..."
        placeholder="Search..."
      />
      <v-btn
        v-if="showFilterBar"
        :icon="true"
        @click="$emit('showFilter')"
      >
        <v-icon>filter_alt</v-icon>
      </v-btn>
    </v-toolbar>
    <v-infinite-scroll
      :empty-text="noDataText"
      :height="height"
      :items="items"
      class="py-2"
      @load="handleLoad"
    >
      <v-list
        :style="computedStyle"
        class="py-0"
        v-bind="pass"
      >
        <template
          v-for="(item, i) in items"
          :key="i"
        >
          <v-list-item @click="$emit('click:item', item)">
            <slot
              :item="item"
              name="item"
            />
          </v-list-item>
          <v-divider
            v-if="i !== items.length - 1"
            :key="i + '-divider'"
            class="my-0"
          />
        </template>
      </v-list>
      <template #error="{ props: passingProps }">
        <v-alert type="error">
          <div class="d-flex justify-space-between align-center">
            {{ errorMsg }}
            <v-btn
              color="white"
              size="small"
              v-bind="passingProps"
              variant="outlined"
            >
              Retry
            </v-btn>
          </div>
        </v-alert>
      </template>
      <template #empty="{ props: passingProps }">
        <slot
          name="no-items"
          v-bind="passingProps"
        >
          <v-alert type="warning">
            No more items!
          </v-alert>
        </slot>
      </template>
    </v-infinite-scroll>
  </div>
</template>

<script lang="ts">
import type {PropType} from "vue"

export const AppListTableProps = {
  endpoint: {
    type: String,
    required: true,
  },
  noDataText: {
    type: String,
    default: "No items found"
  },
  filters: {
    type: Object as PropType<Record<string, any>>,
    default: () => ({})
  },
  method: {
    type: String as PropType<"POST" | "GET" | "PUT" | "PATCH">,
    default: "GET"
  },
  itemPerPage: {
    type: Number,
    default: 10
  },
  showSearchBar: {
    type: Boolean,
    default: false
  },
  showFilterBar: {
    type: Boolean,
    default: false
  },
  threeLine: {
    type: Boolean,
    default: false
  },
  twoLine: {
    type: Boolean,
    default: false
  },
  scrollable: {
    type: Boolean,
    default: false
  },
  height: {
    type: [String, Number],
    default: '100%'
  },
}
</script>
<script lang="ts" setup>
import {ref, computed, useAttrs, toValue, watch, toRefs} from "vue"
import {useDisplay} from "vuetify"
import {VList} from "vuetify/components/VList"
import usePaginationData from "@/composables/usePaginationData"
import {useDebounceFn} from "@vueuse/core"
import cloneDeep from "lodash.clonedeep"

const props = defineProps(AppListTableProps)

// Filtered to VList's own props. The previous spread pushed this component's
// props (`endpoint`, `filters`, `showSearchBar`, ...) onto <v-list>, which
// renders them as stray DOM attributes.
const pass = computed(() => VList.filterProps({...props, ...useAttrs()} as any))

defineEmits(['showFilter', 'click:item'])

const search = ref("")
const items = ref<any[]>([])
const errorMsg = ref<undefined | string>(undefined)
const loading = ref<boolean>(false)
const mergedProps = ref({...toRefs(props.filters), ...(props.showSearchBar ? {search} : {})})
// Holds Vuetify's own `done` callback, so it takes Vuetify's four statuses —
// not the composable's, which has `canceled` instead of `loading`.
const infiniteScrollEvents = ref<((status: 'ok' | 'empty' | 'loading' | 'error') => void) | undefined>(undefined)
const internalStatus = ref<'ok' | 'empty' | 'error' | 'canceled'>('ok')
const {method} = toRefs(props)
// `endpoint` is declared `required: true`, but the props come from a plain
// object literal rather than defineProps<T>(), so vue-tsc never sees that
// requiredness and infers `string | undefined`.
const endpoint = computed(() => props.endpoint as string)

const {pagination, loadData, setPagination} = usePaginationData(endpoint, mergedProps, method)

const {smAndDown} = useDisplay()

defineExpose({
  reload
})

const computedStyle = computed(() => {
  if (props.showSearchBar || props.showFilterBar) {
    return {height: "calc(100% - 56px)", "overflow-y": "scroll"}
  } else {
    return {height: "100%"}
  }
})

const debounceReload = useDebounceFn(() => {
  reload().then()
}, 1000)

const oldFilters = ref(cloneDeep(toValue(props?.filters)))
watch(() => props?.filters, (newValue: any) => {
  newValue = toValue(newValue)

  if (JSON.stringify(newValue) === JSON.stringify(oldFilters)) {
    return
  }
  internalStatus.value = 'ok'
  if (newValue?.search !== oldFilters?.value.search) {
    debounceReload().then()
  } else {
    reload().then()
  }
  oldFilters.value = cloneDeep(newValue)
}, {deep: true})

/**
 * `resetPage` is honoured, not ignored. AppTable has always called
 * `reload(resetPage)`, but this took no arguments and unconditionally reset to
 * page 1 — so `reload(false)`, which exists to refresh a row in place, jumped
 * the user back to the first page every time.
 */
async function reload(resetPage = true) {
  if (resetPage) setPagination({page: 1})
  loading.value = true
  const {data, status, error} = await loadData()
  loading.value = false
  if (status === "canceled") {
    return
  }
  errorMsg.value = error
  items.value = data
  internalStatus.value = status
  if (status !== 'empty') {
    refreshItems()
  }
}

// Vuetify does not re-export InfiniteScrollStatus from the components entry,
// so the four statuses it accepts are spelled out rather than imported.
async function handleLoad({done}: { done: (status: 'ok' | 'empty' | 'loading' | 'error') => void }) {
  if (internalStatus.value === 'empty') {
    done('empty')
    return
  }
  infiniteScrollEvents.value = done
  loading.value = true
  const {data, status, error} = await loadData()
  loading.value = false

  // `canceled` is the composable's own status for a request superseded by a
  // newer one. It is NOT one of Vuetify's four, so passing it straight through
  // handed the scroller a value it does not handle. Worse, the old code then
  // advanced the page — while the superseding request was still fetching this
  // same page — so the skipped page never loaded at all.
  if (status === 'canceled') {
    done('ok')
    return
  }

  errorMsg.value = error
  items.value = items.value.concat(data)
  setPagination({page: pagination.value.page + 1})
  done(status)
  internalStatus.value = status
}

function refreshItems(): void {
  if (infiniteScrollEvents.value) {
    infiniteScrollEvents.value('ok')
  }
}
</script>

<style lang="scss">
.list-table {
  padding: 0 !important;
  position: relative;

  &__header {
    @media (max-width: 600px) {
      position: sticky;
      top: 80px;

      margin-bottom: 4px;
      .v-toolbar__content {
        padding: 0;
      }

      &__search {
        border-radius: 0;
      }
    }
  }

  &__no-items {
    text-align: center;
    font-size: 1.2rem;
    color: #999;
    padding: 1rem;
  }
}
</style>
