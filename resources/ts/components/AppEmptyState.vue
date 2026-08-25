<template>
  <div
    class="app-empty-state d-flex flex-column align-center justify-center text-center py-12 px-4"
  >
    <v-icon
      v-if="icon"
      :icon="icon"
      size="64"
      class="mb-4 text-medium-emphasis"
    />
    <h3 class="text-title-large mb-2">
      {{ title }}
    </h3>
    <p
      v-if="description"
      class="text-body-medium text-medium-emphasis mb-6"
      style="max-width: 400px;"
    >
      {{ description }}
    </p>
    <div
      v-if="$slots.actions"
      class="app-empty-state__actions d-flex ga-2 flex-wrap justify-center"
    >
      <slot name="actions" />
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue"

export default defineComponent({
  name: "AppEmptyState",
  props: {
    title: {
      type: String,
      required: true,
    },
    description: {
      type: String as PropType<string | undefined>,
      default: undefined,
    },
    icon: {
      type: String as PropType<string | undefined>,
      default: "inbox",
    },
  },
})
</script>

<style lang="scss" scoped>
.app-empty-state {
  min-height: 240px;
}
</style>
