<script lang="ts">
import {defineComponent} from "vue"
import {useGlobalSearch} from "@modules/GlobalSearch/resources/ts/composables/useGlobalSearch"

/**
 * The discoverable half of the palette. Cmd+K is not a feature if nobody knows
 * it is there, and the shortcut hint on the button is how they find out.
 */
export default defineComponent({
  name: "AppGlobalSearchButton",
  setup() {
    return {openSearch: useGlobalSearch().openSearch}
  },
  computed: {
    shortcut(): string {
      const mac = typeof navigator !== "undefined" && /Mac|iPhone|iPad/.test(navigator.platform)
      return mac ? "⌘K" : "Ctrl K"
    },
  },
})
</script>

<template>
  <v-btn
    variant="tonal"
    prepend-icon="search"
    class="text-none"
    @click="openSearch()"
  >
    Search
    <template #append>
      <span class="text-body-small text-medium-emphasis">{{ shortcut }}</span>
    </template>
  </v-btn>
</template>
