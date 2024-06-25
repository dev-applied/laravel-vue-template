<template>
  <div class="list-table">
    <v-toolbar
      v-show="enableSearch || enableFilter"
      color="white"
      height="40"
      :flat="smAndDown"
      class="list-table__header"
    >
      <v-text-field
        v-if="enableSearch"
        v-model="search"
        dense
        outlined
        hide-details
        class="list-table__header__search"
        placeholder="Search..."
        :readonly="loading"
        label="Search..."
      />
      <v-btn
        v-if="enableFilter"
        icon
        @click="$emit('showFilter')"
      >
        <v-icon>mdi-filter</v-icon>
      </v-btn>
    </v-toolbar>
    <div
      class="list-table__content"
      :style="{ height: convertToUnit(height), 'overflow-y': scrollable ? 'scroll' : 'auto' }"
    >
      <v-alert
        v-if="error"
        type="error"
      >
        {{ error }}
      </v-alert>
      <div v-else-if="loading || internalItems.length">
        <v-list
          :three-line="threeLine"
          :two-line="twoLine"
          :style="computedStyle"
          v-bind="$attrs"
          class="py-0"
        >
          <template v-for="(item, i) in internalItems" :key="i">
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
        <v-card
          v-if="!finished"
          v-intersect="loadData"
        />
        <div class="d-flex justify-center mt-2">
          <v-progress-circular
            v-if="loading"
            indeterminate
          />
        </div>
      </div>
      <div v-else>
        <slot name="no-items">
          <div class="list-table__no-items">
            {{ noDataText || "No Items" }}
          </div>
        </slot>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue"
import convertToUnit from "@/utils/convertToUnit"
import { type StoreDefinition } from "pinia"
import { useDebounceFn } from "@vueuse/core"
import axios from "@/plugins/axios"
import errorHandler from "@/plugins/errorHandler"
import { useDisplay } from "vuetify"

interface Pagination {
  sort: null;
  direction: "DESC";
  page: 0;
  perPage: 10;
  count: 0;
}

export interface Props {
  endpoint: string;
  fieldName: string | null;
  additionalRequestData: Record<string, any>;
  method: "POST" | "GET" | "PUT" | "PATCH";
  enableSearch: boolean;
  enableFilter: boolean;
  storeModule: StoreDefinition | null;
  threeLine: boolean;
  twoLine: boolean;
  scrollable: boolean;
  height: string | number;
  noDataText: string | null;
  itemsPerPage: number | null;
}

const props = withDefaults(defineProps<Props>(), {
  fieldName: null,
  additionalRequestData: () => ({}),
  method: "GET",
  enableSearch: false,
  enableFilter: false,
  storeModule: null,
  threeLine: false,
  twoLine: false,
  scrollable: true,
  height: "100%",
  noDataText: null,
  itemsPerPage: null
})

const emit = defineEmits(['current-items'])

const search = ref("")
const items = ref<any[]>([])
const loading = ref(false)
const error = ref<string | null | boolean>(null)
const finished = ref(false)
const pagination = ref<Pagination>({
  sort: null,
  direction: "DESC",
  page: 0,
  perPage: 10,
  count: 0
})
const { smAndDown } = useDisplay()

const store = props.storeModule ? props.storeModule() : null

const internalItems = computed({
  get(): any[] {
    return store && props.fieldName ? store[props.fieldName] : items.value
  },
  set(val: any[]) {
    if (store && props.fieldName) {
      store[props.fieldName] = val
    } else {
      items.value = val
    }
  }
})

const internalPagination = computed({
  get() {
    return store ? store.pagination : pagination.value
  },
  set(val) {
    if (store) {
      store.pagination = val
    } else {
      pagination.value = val
    }
  }
})

const computedStyle = computed(() => {
  if (props.enableSearch || props.enableFilter) {
    return { height: "calc(100% - 56px)", "overflow-y": "scroll" }
  } else {
    return { height: "100%" }
  }
})

const debounceReload = useDebounceFn(() => {
  reload()
}, 1000, { maxWait: 5000 })

watch(search, debounceReload)

watch(props.additionalRequestData, debounceReload, { deep: true })

onMounted(async () => {
  await loadData()
})

onBeforeUnmount(() => {
  internalItems.value = []
  internalPagination.value = {
    sort: null,
    direction: "DESC",
    page: 0,
    perPage: 10,
    count: 0
  }
})

async function loadData() {
  if (finished.value || error.value || loading.value) {
    return
  }
  internalPagination.value.page += 1
  error.value = false
  loading.value = true

  let config
  if (props.method === "GET") {
    config = {
      params: {
        search: search.value,
        ...internalPagination.value,
        ...props.itemsPerPage ? { perPage: props.itemsPerPage } : {},
        ...props.additionalRequestData
      }
    }
  } else {
    config = {
      pagination: internalPagination.value,
      ...props.itemsPerPage ? { perPage: props.itemsPerPage } : {},
      ...props.additionalRequestData,
      search: search.value
    }
  }

  const { data, status } = await axios[props.method.toLowerCase()](props.endpoint, config).catch((e) => e)

  loading.value = false
  if (errorHandler(status, data.message, data.errors)) {
    error.value = data.message
    return
  }

  internalItems.value = internalItems.value.concat(data.data)
  emit("current-items", internalItems.value)
  internalPagination.value.page = data.current_page
  internalPagination.value.pageStop = data.to ?? 0
  finished.value = data.last_page <= data.current_page
}

function reload(resetPage = true) {
  if (resetPage) {
    internalPagination.value.page = 0
  }
  internalItems.value = []
  error.value = false
  finished.value = false
  loadData()
}
</script>

<style lang="scss">
.list-table {
  padding: 0 !important;
  position: relative;

  &__header {
    @media #{map-get($display-breakpoints, 'sm-and-down')} {
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
