<template>
  <div>
    <v-skeleton-loader
      v-if="initialLoad"
      type="table"
    />
    <v-data-table
      v-show="!initialLoad"
      ref="table"
      :class="{'striped': striped, 'table--styled': styled}"
      :disable-sort="!sortable"
      :items="items"
      :items-per-page="pagination.perPage"
      :loading="isLoading"
      disable-filtering
      hide-default-footer
      v-bind="$attrs"
      v-on="$listeners"
      @update:sort-by="updateSortBy"
      @update:sort-desc="updateSortDirection"
    >
      <template
        v-for="(_, name) in $slots"
        :slot="name"
      >
        <slot :name="name" />
      </template>
      <template
        v-for="(_, name) in $scopedSlots"
        #[name]="data"
      >
        <slot
          :name="name"
          v-bind="data"
        />
      </template>
      <template
        v-if="error"
        #body
      >
        <div class="d-flex">
          <v-alert
            class="mt-4 mx-auto"
            icon="mdi-close-circle-outline"
            outlined
            type="error"
          >
            {{ error }}
          </v-alert>
        </div>
      </template>
      <template
        v-if="!$slots.footer && !hideFooter"
        #footer
      >
        <div class="v-data-footer">
          <div class="v-data-footer__select">
            {{ itemsPerPageText }}
            <v-select
              v-model="pagination.perPage"
              :items="computedDataItemsPerPageOptions"
              auto
              class="v-data-footer__select"
              hide-details
              min-width="75px"
            />
          </div>
          <div class="v-data-footer__pagination">
            {{ pagination.pageStart }} - {{ pagination.pageStop }} of {{ pagination.count }}
          </div>
          <loading-table-arrow
            :class="{disabled: pagination.page <= 1 || loading}"
            :loading="formerLoading"
            color="#808080"
            left
            @click="formerPage"
          />
          <loading-table-arrow
            :class="{disabled: pagination.page + 1 > numberOfPages || loading}"
            :loading="nextLoading"
            color="#808080"
            @click="nextPage"
          />
        </div>
      </template>
    </v-data-table>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue"
import LoadingTableArrow from "@/components/AppLoadingTableArrow.vue"
import debounce from "@/mixins/debounce"

export default defineComponent({
  components: { LoadingTableArrow },
  mixins: [debounce],
  inheritAttrs: false,
  props: {
    endpoint: {
      type: String,
      required: true
    },
    additionalRequestData: {
      type: Object as PropType<Record<string, unknown>>,
      required: false,
      default: () => {
        return {}
      }
    },
    method: {
      type: String as PropType<"POST" | "GET" | "PUT" | "PATCH">,
      default: "GET",
      validator: (value: string) => {
        return ["POST", "GET", "PUT", "PATCH"].includes(value)
      }
    },
    loadingOverride: {
      type: Boolean as PropType<boolean | null>,
      default: null
    },
    striped: {
      type: Boolean as PropType<boolean>,
      default: false
    },
    styled: {
      type: Boolean as PropType<boolean>,
      default: false
    },
    sortable: {
      type: Boolean as PropType<boolean>,
      default: true
    },
    search: {
      type: [String, undefined, null] as PropType<string | undefined | null>,
      default: undefined
    },
    autoReloadAdditionalRequestData: {
      type: Boolean as PropType<boolean>,
      default: false
    },
    hideFooter: {
      type: Boolean as PropType<boolean>,
      default: false
    },
    itemsPerPage: {
      type: Number as PropType<number>,
      default: 10
    }
  },
  data() {
    return {
      pagination: {
        sort: null as null | string,
        direction: "DESC",
        page: 1,
        perPage: this.itemsPerPage,
        count: 0
      },
      items: [] as any[],
      initialLoad: true as boolean,
      loading: false as boolean,
      itemsPerPageOptions: [
        5,
        10,
        15,
        20,
        50,
        100,
        250,
      ],
      formerLoading: false as boolean,
      nextLoading: false as boolean,
      ignorePaginationUpdate: false as boolean,
      error: false as false | string,
      abortController: null as null | AbortController
    }
  },
  computed: {
    itemsPerPageText(): string {
      return this.$vuetify.lang.t("$vuetify.dataFooter.itemsPerPageText")
    },
    numberOfPages(): number {
      return Math.ceil(this.pagination.count / this.pagination.perPage)
    },
    computedDataItemsPerPageOptions(): { text: string, value: number }[] {
      return this.itemsPerPageOptions.map((option) => {
        return {
          text: String(option),
          value: option
        }
      })
    },
    isLoading(): boolean {
      if (this.loadingOverride !== null) {
        return this.loadingOverride
      } else {
        return this.loading
      }
    }
  },
  watch: {
    "pagination.perPage"(val, oldval) {
      if (val !== oldval) {
        this.pagination.page = 1
      }
    },
    pagination: {
      async handler() {
        if (this.initialLoad || this.ignorePaginationUpdate) {
          return
        }
        await this.loadData()
        this.nextLoading = false
        this.formerLoading = false
      },
      deep: true
    },
    search() {
      this.debouncer(() => {
        this.reload()
      })
    },
    additionalRequestData: {
      handler() {
        if (!this.autoReloadAdditionalRequestData) {
          return
        }
        this.reload()
      },
      deep: true
    },
    endpoint() {
      this.reload()
    }
  },
  mounted() {
    this.loadData()
  },
  methods: {
    reload(resetPage = true) {
      if(resetPage) {
        this.pagination.page = 1
      }
      this.loadData()
    },
    async loadData() {
      this.error = false
      if (this.abortController) {
        this.abortController.abort("canceled")
      }
      this.abortController = new AbortController()
      this.loading = true
      if (this.method === "GET") {
        // eslint-disable-next-line no-var
        var { data, status } = await this.$http.get(this.endpoint, {
          signal: this.abortController.signal,
          params: {
            search: this.search,
            ...this.pagination,
            ...this.additionalRequestData
          }
        }).catch(e => e)
      } else {
        // eslint-disable-next-line no-redeclare
        var { data, status } = await this.$http[this.method.toLowerCase()](this.endpoint, {
          pagination: this.pagination,
          ...this.additionalRequestData,
          search: this.search
        }, { signal: this.abortController.signal }).catch(e => e)
      }
      const error = this.$error(status, data.message, data.errors)

      if (error && data.message !== "canceled") {
        this.initialLoad = false
        this.loading = false
        this.error = data.message
        return
      } else if (error) {
        return
      }
      this.loading = false
      this.initialLoad = false
      this.items = data.data
      this.ignorePaginationUpdate = true
      this.pagination.page = data.current_page
      this.pagination.perPage = Number(data.per_page)
      this.pagination.count = data.total
      this.pagination.pageStart = data.from ?? 0
      this.pagination.pageStop = data.to ?? 0
      this.$nextTick(() => {
        this.ignorePaginationUpdate = false
      })
      this.$emit("loaded", this.pagination.count)
    },
    nextPage() {
      if (this.loading || this.pagination.page >= this.numberOfPages) {
        return
      }
      this.pagination.page = this.pagination.page + 1
      this.nextLoading = true
    },
    formerPage() {
      if (this.loading || this.pagination.page <= 1) {
        return
      }
      this.pagination.page = this.pagination.page - 1
      this.formerLoading = true
    },
    updateSortBy(sort: string) {
      // TODO: look into getting multisort to work here
      // this.pagination.sort = sort[0]
      this.pagination.sort = sort
      this.$emit("sortUpdate", this.pagination.sort ? this.pagination.sort + " " + this.pagination.direction : undefined)
    },
    updateSortDirection(direction: boolean) {
      this.pagination.direction = direction ? "desc" : "asc"
      this.$emit("sortUpdate", this.pagination.sort ? this.pagination.sort + " " + this.pagination.direction : undefined)
    }
  }
})
</script>

<style lang="scss" scoped>
.striped::v-deep {
  tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 0, 0, .05);
  }
}

.table--styled::v-deep tbody tr:nth-of-type(odd) {
  background-color: rgba(232, 234, 246, .3);
}

.table--styled::v-deep tbody tr:nth-of-type(odd):hover {
  background-color: rgba(230, 232, 244, .5) !important;
}

.table--styled::v-deep tbody tr:nth-of-type(even):hover {
  background-color: rgba(200, 210, 210, .15) !important;
}

.table--styled::v-deep tbody tr {
  transform: scale(1);
  transition: all 0.2s;
  /*box-shadow: 0 0 0 -0 rgba(0,0,0,.2),0 0 0 0 rgba(0,0,0,.14),0 0 0 0 rgba(0,0,0,.12)!important;*/
  border-radius: 4px;
  position: relative;
}

.table--styled::v-deep tbody tr:hover {
  transform: scale(1.005);
  /*box-shadow: 0 3px 5px -1px rgba(0,0,0,.2),0 6px 10px 0 rgba(0,0,0,.14),0 1px 18px 0 rgba(0,0,0,.12)!important;*/
}

.table--styled::v-deep tbody tr::after {
  transition: opacity 0.3s ease-in-out;
  /*box-shadow: 0 3px 5px -1px rgba(0,0,0,.2),0 6px 10px 0 rgba(0,0,0,.14),0 1px 18px 0 rgba(0,0,0,.12)!important;*/
  box-shadow: 0 2px 15px -5px rgba(0, 0, 0, 0.3);
  content: '';
  position: absolute;
  z-index: -1;
  width: 100%;
  height: 100%;
  opacity: 0;
  border-radius: 4px;
  left: 0;
  top: 0;
}

.table--styled::v-deep tbody tr:hover::after {
  opacity: 1;
}

.table--styled::v-deep .v-data-table__wrapper {
  overflow-x: visible !important;
  padding: 0 8px;
}

.table--styled::v-deep tbody td {
  border-bottom: 0 !important;
}

.table--styled::v-deep .v-data-footer {
  border-top: 0 !important;
}
</style>
