<template>
  <v-autocomplete
    v-if="!hideIfNoData || items.length > 0"
    ref="standard_ac"
    v-model="internal_value"
    :cache-items="cacheItems"
    :disabled="disabled"
    :items="items"
    :loading="loading"
    :readonly="loading && disabledOnLoading"
    v-model:search-input="search"
    v-bind="$attrs"
  >
    <template v-for="(_, name) in $slots" v-slot:[name]="slotData">
      <slot :name="name" v-bind="slotData" />
    </template>
  </v-autocomplete>
</template>

<script lang="ts">
import debounce from "lodash.debounce"
import { defineComponent, PropType } from "vue"

export default defineComponent({
  inheritAttrs: false,
  props: {
    additionalItems: {
      type: Array,
      default: () => []
    },
    cacheItems: {
      type: Boolean,
      default: true
    },
    value: {
      type: [String, Number, Array, Object] as PropType<any>,
      required: true
    },
    mandatory: {
      type: Boolean,
      default: false
    },
    disabledOnLoading: {
      type: Boolean,
      default: true
    },
    endpoint: {
      type: String,
      required: true
    },
    fieldName: {
      type: String,
      required: true
    },
    static: {
      type: Boolean,
      default: false
    },
    hideIfNoData: {
      type: Boolean,
      default: false
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      items: [],
      loading: false,
      search: "",
      selected: []
    }
  },
  computed: {
    internal_value: {
      get() {
        return this.value
      },
      set(val) {
        this.$emit("input", val)
      }
    }
  },
  watch: {
    search(val) {
      if (!this.static) this.debouncer(async () => this.searchItems(val))
    },
    "$refs.standard_ac.selectedItems": {
      handler() {
        this.selected = this.$refs.standard_ac.selectedItems
      }
    },
    endpoint() {
      this.searchItems(this.search)
    },
    disabled() {
      if (!this.disabled) {
        this.searchItems(this.search)
      }
    }
  },
  mounted() {
    this.searchItems(this.search)
    if (this.$refs.standard_ac) {
      this.$watch(
        () => {
          return this.$refs.standard_ac.selectedItems
        },
        (val) => {
          this.selected = val
        }
      )
    }
  },
  methods: {
    async searchItems(search) {
      if (this.disabled) return
      this.loading = true
      const params = {
        search: search
      }

      if (this.internal_value) {
        let value = Array.isArray(this.internal_value) ? this.internal_value : [this.internal_value]
        let key = "id"
        if (this.$attrs["item-value"]) {
          key = this.$attrs["item-value"]
        }

        params.key = key

        params.selected = value.map(item => {
          if (typeof item === "object") {
            return item[key]
          }
          return item
        })
      }

      let { data, status } = await this.$http.get(this.endpoint, {
        params
      }).catch(e => e)
      this.loading = false
      if (this.$error(status, data.message, data.errors)) return
      this.items = this.additionalItems.concat(data[this.fieldName])
    },
    debouncer: debounce(function(callback) {
      callback()
    }, 500),
    clear() {
      this.$refs.standard_ac.clearableCallback()
    }
  }
})
</script>

<style scoped>

</style>
