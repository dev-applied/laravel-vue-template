<template>
  <div class="list-table">
    <v-toolbar
      v-show="showSearchBar || showFilterBar"
      color="white"
      height="40"
      :flat="smAndDown"
      class="list-table__header"
    >
      <v-text-field
        v-if="showSearchBar"
        v-model="search"
        dense
        hide-details
        class="list-table__header__search"
        placeholder="Search..."
        :readonly="loading"
        label="Search..."
      />
      <v-btn
        v-if="showFilterBar"
        :icon="true"
        @click="$emit('showFilter')"
      >
        <v-icon>mdi-filter</v-icon>
      </v-btn>
    </v-toolbar>
    <v-infinite-scroll
      :height="height"
      :items="items"
      @load="handleLoad"
      :empty-text="noDataText"
      class="py-2"
    >
      <v-list
        :style="computedStyle"
        v-bind="pass"
        class="py-0"
      >
        <template
          v-for="(item, i) in items"
          :key="i"
        >
          <v-list-item @click="$emit('click:item', item)">
            <slot
              name="item"
              :item="item"
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
              variant="outlined"
              v-bind="passingProps"
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
import type { PropType } from "vue"

export const AppListTableProps = {
  endpoint: {
    type: String,
    required: true,
    default: ''
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
<script setup lang="ts">
import {ref, computed, useAttrs, toValue, watch, toRefs} from "vue"
import { useDisplay } from "vuetify"
import usePaginationData from "@/composables/usePaginationData"
import {useDebounceFn} from "@vueuse/core"
import {cloneDeep} from "lodash"

const props = defineProps(AppListTableProps)

const pass = {...props, ...useAttrs()}

defineEmits(['showFilter', 'click:item'])

const search = ref("")
const items = ref<any[]>([])
const errorMsg = ref<undefined | string>(undefined)
const loading = ref<boolean>(false)
const mergedProps = ref({...toRefs(props.filters), ...(props.showSearchBar ? { search } : {})})
const infiniteScrollEvents = ref<((value: 'ok' | 'empty' | 'error' | 'canceled') => void) | undefined>(undefined)
const internalStatus = ref<'ok' | 'empty' | 'error' | 'canceled'>('ok')
const {endpoint, method} = toRefs(props)

const { pagination, loadData, setPagination } = usePaginationData(endpoint, mergedProps, method)

const { smAndDown } = useDisplay()

defineExpose({
  reload
})

const computedStyle = computed(() => {
  if (props.showSearchBar || props.showFilterBar) {
    return { height: "calc(100% - 56px)", "overflow-y": "scroll" }
  } else {
    return { height: "100%" }
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
  if(newValue?.search !== oldFilters?.value.search) {
    debounceReload().then()
  } else {
    reload().then()
  }
  oldFilters.value = cloneDeep(newValue)
}, { deep: true })

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
  internalStatus.value = status
  if(status !== 'empty') {
    refreshItems()
  }
}

async function handleLoad({done}: { done: (value: 'ok' | 'empty' | 'error' | 'canceled') => void }) {
  if(internalStatus.value === 'empty') {
    done('empty')
    return
  }
  infiniteScrollEvents.value = done
  loading.value = true
  const {data, status, error} = await loadData()
  loading.value = false
  errorMsg.value = error
  items.value = items.value.concat(data)
  setPagination({ page: pagination.value.page + 1})
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
