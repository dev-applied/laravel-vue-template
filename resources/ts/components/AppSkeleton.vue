<template>
  <div class="app-skeleton">
    <v-skeleton-loader
      v-for="i in count"
      :key="i"
      :type="resolvedType"
    />
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue"

type Preset = "card" | "list-item" | "list-item-avatar" | "list-item-two-line" | "list-item-avatar-two-line" | "table-row" | "table-row-divider" | "paragraph" | "text" | "avatar" | "heading" | "image"

export default defineComponent({
  name: "AppSkeleton",
  props: {
    /** Vuetify skeleton-loader `type`. Common: list-item-avatar-two-line, table-row, card, paragraph. */
    type: {
      type: String as PropType<Preset>,
      default: "list-item-avatar-two-line",
    },
    /** Render this many rows. Useful for list/table placeholders. */
    count: {
      type: Number,
      default: 1,
    },
  },
  computed: {
    resolvedType(): string {
      return this.type
    },
  },
})
</script>

<style lang="scss" scoped></style>
