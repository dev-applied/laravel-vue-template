<template>
  <div class="list-table">
    <v-toolbar
      v-show="enableSearch || enableFilter"
      color="white"
      height="40"
      :flat="$vuetify.breakpoint.smAndDown"
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
          :style="style"
          v-bind="$attrs"
          class="py-0"
        >
          <template v-for="(item, i) in internalItems">
            <slot
              name="list-item"
              :item="item"
            >
              <v-list-item
                :key="i"
                @click="$emit('click:item', item)"
              >
                <slot
                  name="item"
                  :item="item"
                />
              </v-list-item>
            </slot>
            <v-divider
              v-if="i !== items.length - 1"
              :key="i + '-divider'"
              class="my-0"
            />
          </template>
        </v-list>
        <v-card
          v-if="!finished && !loading"
          id="intersect-observer"
          v-intersect="handleIntersect"
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
            {{ noDataText || 'No Items' }}
          </div>
        </slot>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import debounce from "@/mixins/debounce"
import convertToUnit from "@/utils/convertToUnit"
import { defineComponent } from "vue"
import type { PropType } from "vue"
import type { StoreDefinition } from "pinia"

interface Pagination {
  sort: null
  direction: "DESC"
  page: 0
  perPage: 10
  count: 0
}

export default defineComponent({
  mixins: [debounce],
  inheritAttrs: false,
  props: {
    endpoint: {
      type: String,
      default: ''
    },
    fieldName: {
      type: String,
      default: null
    },
    additionalRequestData: {
      type: Object,
      required: false,
      default: () => {
        return {}
      }
    },
    method: {
      type: String,
      default: "GET",
      validator(value: string) {
        // The value must match one of these strings
        return ["POST", "GET", "PUT", "PATCH"].includes(value)
      }
    },
    enableSearch: {
      type: Boolean,
      default: false
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
      default: "100%"
    },
    noDataText: {
      type: String,
      default: null
    },
    itemsPerPage: {
      type: Number,
      default: null,
    },
    items: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      pagination: {
        sort: null,
        direction: "DESC",
        page: 0,
        perPage: 10,
        count: 0
      },
      search: '',
      loadedItems: [] as any[],
      loading: false,
      error: null as string | null | boolean,
      finished: false
    }
  },
  computed: {
    internalItems: {
      get() {
        if (this.storeModule) {
          return this.storeModule()[this.fieldName]
        }

        return this.items.length ? this.items : this.loadedItems
      },
      set(val: any[]) {
        if (this.storeModule) {
          // @ts-ignore
          this.storeModule()[this.fieldName] = val
        } else {
          this.loadedItems = val
        }
      }
    },
    internalPagination: {
      get() {
        if (this.storeModule) {
          return this.storeModule().pagination
        }

        return this.pagination
      },
      set(val: Pagination) {
        if (this.storeModule) {
          this.storeModule().pagination = val
        } else {
          this.pagination = val
        }
      }
    },
    style() {
      if (this.enableSearch || this.enableFilter) {
        return {height: "calc(100% - 56px)", "overflow-y": "scroll"}
      } else {
        return {height: "100%"}
      }
    }
  },
  watch: {
    search() {
      this.debouncer(() => {
        this.reload()
      })
    },
    additionalRequestData: {
      handler() {
        this.debouncer(() => {
          this.reload()
        })
      },
      deep: true
    }
  },
  async mounted() {
    await this.loadData()
  },
  beforeDestroy() {
    this.internalItems = []
    this.internalPagination = {
      sort: null,
      direction: "DESC",
      page: 0,
      perPage: 10,
      count: 0
    }
  },
  methods: {
    reload() {
      this.internalPagination.page = 0
      this.internalItems = []
      this.error = false
      this.finished = false
      this.loadData()
    },
    async loadData() {
      if (this.finished || this.error || this.loading || !this.endpoint) {
        return
      }
      this.internalPagination.page += 1

      this.error = false
      this.loading = true
      let config
      if (this.method === "GET") {
        config = {
          params: {
            search: this.search,
            ...this.internalPagination,
            ...this.itemsPerPage ? {perPage: this.itemsPerPage} : {},
            // @ts-ignore
            ...this.additionalRequestData
          }
        }
      } else {
        config = {
          pagination: this.internalPagination,
          ...this.itemsPerPage ? {perPage: this.itemsPerPage} : {},
          // @ts-ignore
          ...this.additionalRequestData,
          search: this.search
        }
      }

      // @ts-ignore
      const {data, status} = await this.$http[this.method.toLowerCase()](
        this.endpoint,
        config
      ).catch((e) => e)
      this.loading = false
      if (this.$error(status, data.message, data.errors)) {
        this.error = data.message
        return
      }

      this.internalItems = this.internalItems.concat(data.data)
      this.$emit('current-items', this.internalItems)
      this.internalPagination.page = data.current_page
      this.internalPagination.pageStop = data.to ?? 0
      this.internalPagination.count = data.total
      this.finished = data.last_page <= data.current_page
      this.$emit("loaded", this.pagination)
    },
    convertToUnit,
    handleIntersect(entries) {
      if (entries[0].isIntersecting) {
        this.loadData()
      }
    }
  },
})
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
