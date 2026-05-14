<template>
  <v-card variant="outlined">
    <v-card-text class="pa-4">
      <v-row dense>
        <v-col
          cols="12"
          md="5"
        >
          <v-text-field
            v-model="search"
            label="Search name or description"
            prepend-inner-icon="search"
            density="comfortable"
            hide-details
            clearable
          />
        </v-col>
        <v-col
          cols="6"
          md="3"
        >
          <v-select
            v-model="status"
            :items="statusOptions"
            label="Status"
            density="comfortable"
            hide-details
            clearable
          />
        </v-col>
        <v-col
          cols="6"
          md="4"
        >
          <app-auto-complete
            v-model="ownerId"
            endpoint="users"
            label="Owner"
            :item-title="(item: any) => item.full_name"
            :item-value="(item: any) => item.id"
            density="comfortable"
            hide-details
            clearable
          />
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue"
import debounce from "lodash.debounce"

interface Filters {
  search:   string
  status:   string | null
  owner_id: number | null
}

export default defineComponent({
  name: "ItemFilterBar",
  props: {
    modelValue: {
      type: Object as PropType<Filters>,
      required: true,
    },
  },
  emits: ["update:modelValue"],
  data() {
    return {
      // Local mirrors of the filter values — search debounces before emit
      // to avoid one request per keystroke.
      search:   this.modelValue.search,
      status:   this.modelValue.status,
      ownerId:  this.modelValue.owner_id,
      statusOptions: [
        { title: "Draft",    value: "draft" },
        { title: "Active",   value: "active" },
        { title: "Archived", value: "archived" },
      ],
    }
  },
  watch: {
    search: {
      handler: debounce(function (this: any, v: string) {
        this.emitFilters({ search: v ?? "" })
      }, 300),
    },
    status(v: string | null) {
      this.emitFilters({ status: v })
    },
    ownerId(v: number | null) {
      this.emitFilters({ owner_id: v })
    },
    modelValue: {
      deep: true,
      handler(newVal: Filters) {
        // Keep local state in sync when parent resets filters externally.
        this.search  = newVal.search
        this.status  = newVal.status
        this.ownerId = newVal.owner_id
      },
    },
  },
  methods: {
    emitFilters(patch: Partial<Filters>) {
      this.$emit("update:modelValue", { ...this.modelValue, ...patch })
    },
  },
})
</script>

<style lang="scss" scoped></style>
